from flask import Flask, request, jsonify
from flask_cors import CORS
import requests
import json
import mysql.connector
from mysql.connector import Error
from datetime import datetime, timedelta

app = Flask(__name__)
CORS(app)

# Database configuration
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': 'Password1$',
    'database': 'speegotest'
}

def get_db_connection():
    """Create and return a database connection"""
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        return connection
    except Error as e:
        print(f"Database connection error: {e}")
        return None


# ======================================================
# EXISTING CORE FUNCTIONS 
# ======================================================

def check_warranty_status(product_id, purchase_date):
    """Check if product is still under warranty"""
    conn = get_db_connection()
    if not conn:
        return None
    try:
        cursor = conn.cursor(dictionary=True)
        cursor.execute("""
            SELECT w.WarrantyID, w.ProductID, w.CustomerID, 
                   w.Warranty_StartDate, w.Warranty_EndDate, w.Warranty_Status,
                   p.Product_Name
            FROM WARRANTY w
            JOIN PRODUCT p ON w.ProductID = p.ProductID
            WHERE w.ProductID = %s
            ORDER BY w.Warranty_StartDate DESC
            LIMIT 1
        """, (product_id,))
        
        warranty = cursor.fetchone()
        if not warranty:
            purchase_dt = datetime.strptime(purchase_date, '%Y-%m-%d')
            warranty_end = purchase_dt + timedelta(days=365)
            today = datetime.now()
            is_active = today <= warranty_end
            days_remaining = (warranty_end - today).days if is_active else 0
            return {
                'has_warranty': False,
                'is_active': is_active,
                'warranty_end_date': warranty_end.strftime('%Y-%m-%d'),
                'days_remaining': max(0, days_remaining),
                'coverage_type': 'Standard 1-Year Warranty'
            }

        warranty_end = warranty['Warranty_EndDate']
        today = datetime.now().date()
        is_active = warranty['Warranty_Status'] == 'Active' and today <= warranty_end
        days_remaining = (warranty_end - today).days if is_active else 0
        
        return {
            'has_warranty': True,
            'is_active': is_active,
            'warranty_id': warranty['WarrantyID'],
            'warranty_end_date': warranty_end.strftime('%Y-%m-%d'),
            'warranty_start_date': warranty['Warranty_StartDate'].strftime('%Y-%m-%d'),
            'days_remaining': max(0, days_remaining),
            'status': warranty['Warranty_Status'],
            'product_name': warranty['Product_Name']
        }
    except Error as e:
        print(f"Error checking warranty: {e}")
        return None
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()


def get_service_costs():
    """Fetch service costs from service_cost table"""
    conn = get_db_connection()
    if not conn:
        return {}
    try:
        cursor = conn.cursor(dictionary=True)
        cursor.execute("""
            SELECT sr.service_type, sc.labor_cost, sc.parts_cost, sc.total_cost, sc.payment_status
            FROM service_cost sc
            JOIN service_request sr ON sc.service_request_id = sr.service_request_id
        """)
        costs = cursor.fetchall()
        return {c['service_type']: {'EstimatedCost': c['total_cost']} for c in costs}
    except Error as e:
        print(f"Error fetching service costs: {e}")
        return {}
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()


def get_available_products(category=None):
    """Fetch available products from inventory"""
    conn = get_db_connection()
    if not conn:
        return []
    try:
        cursor = conn.cursor(dictionary=True)
        query = """
            SELECT p.ProductID, p.Product_Name, p.Category, p.Price, p.Stock,
                   i.Stock_Level, i.Low_Stock, i.Availability
            FROM PRODUCT p
            JOIN INVENTORY i ON p.ProductID = i.ProductID
            WHERE i.Stock_Level > i.Low_Stock AND i.Availability = 'Available'
        """
        params = []
        if category:
            query += " AND p.Category = %s"
            params.append(category)
        cursor.execute(query, params) if params else cursor.execute(query)
        products = cursor.fetchall()
        return products
    except Error as e:
        print(f"Error fetching products: {e}")
        return []
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()


def get_product_recommendations(keywords):
    """Get product recommendations based on keywords"""
    conn = get_db_connection()
    if not conn:
        return []
    try:
        cursor = conn.cursor(dictionary=True)
        keyword_pattern = f"%{keywords}%"
        cursor.execute("""
            SELECT p.ProductID, p.Product_Name, p.Category, p.Price, p.Stock,
                   i.Stock_Level, i.Availability
            FROM PRODUCT p
            JOIN INVENTORY i ON p.ProductID = i.ProductID
            WHERE (p.Product_Name LIKE %s OR p.Category LIKE %s)
            AND i.Stock_Level > 0 AND i.Availability = 'Available'
            LIMIT 5
        """, (keyword_pattern, keyword_pattern))
        return cursor.fetchall()
    except Error as e:
        print(f"Error getting recommendations: {e}")
        return []
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()


def save_diagnosis_to_db(diagnosis_data):
    """Save diagnosis to database and update corresponding service request status"""
    conn = get_db_connection()
    if not conn:
        return None
    try:
        cursor = conn.cursor()
        insert_query = """
            INSERT INTO service_diagnosis
            (ServiceRequestID, DiagnosisDetails, TechnicianName, FindingsDate)
            VALUES (%s, %s, %s, %s)
        """
        cursor.execute(insert_query, (
            diagnosis_data.get('service_request_id'),
            diagnosis_data.get('diagnosis_details'),
            diagnosis_data.get('technician_name', 'SpeegoPal AI'),
            datetime.now()
        ))
        diagnosis_id = cursor.lastrowid
        update_query = """
            UPDATE service_request SET status = 'Diagnosed' WHERE service_request_id = %s
        """
        cursor.execute(update_query, (diagnosis_data.get('service_request_id'),))
        conn.commit()
        return diagnosis_id
    except Error as e:
        print(f"Error saving diagnosis: {e}")
        conn.rollback()
        return None
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()


def save_service_request_to_db(request_data):
    """Save service request to database"""
    conn = get_db_connection()
    if not conn:
        return None
    try:
        cursor = conn.cursor()
        insert_query = """
            INSERT INTO service_request 
            (customer_id, admin_id, service_type, problem_description, appointment_date, status, product_id)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
        """
        cursor.execute(insert_query, (
            request_data.get('customer_id', 1),
            request_data.get('admin_id', None),
            request_data.get('service_type', 'Repair'),
            request_data.get('problem_description'),
            request_data.get('appointment_date'),
            'Pending Diagnosis',
            request_data.get('product_id', 1)
        ))
        conn.commit()
        return cursor.lastrowid
    except Error as e:
        print(f"Error saving service request: {e}")
        conn.rollback()
        return None
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()

# ======================================================
# NEW FUNCTION: RECOMMENDED FOR YOU
# ======================================================

def get_recommended_for_you(customer_id=None, limit=12):
    """
    Get personalized product recommendations for a customer
    Based on:
    1. Purchase history (similar categories)
    2. Service request history (related products)
    3. Popular products (fallback)
    """
    conn = get_db_connection()
    if not conn:
        return []
    
    try:
        cursor = conn.cursor(dictionary=True)
        recommended_products = []
        
        if customer_id:
            # Get customer's purchase history categories
            cursor.execute("""
                SELECT DISTINCT p.Category
                FROM PRODUCT p
                JOIN ORDER_ITEM oi ON p.ProductID = oi.ProductID
                JOIN ORDERS o ON oi.OrderID = o.OrderID
                WHERE o.CustomerID = %s
                LIMIT 3
            """, (customer_id,))
            purchased_categories = [row['Category'] for row in cursor.fetchall()]
            
            # Get products from same categories (excluding already purchased)
            if purchased_categories:
                category_placeholders = ','.join(['%s'] * len(purchased_categories))
                cursor.execute(f"""
                    SELECT DISTINCT p.ProductID, p.Product_Name, p.Category, p.Price, 
                           p.Stock, i.Stock_Level, i.Availability,
                           'Based on your purchases' as recommendation_reason
                    FROM PRODUCT p
                    JOIN INVENTORY i ON p.ProductID = i.ProductID
                    WHERE p.Category IN ({category_placeholders})
                    AND p.ProductID NOT IN (
                        SELECT oi.ProductID FROM ORDER_ITEM oi JOIN ORDERS o ON oi.OrderID = o.OrderID WHERE o.CustomerID = %s
                    )
                    AND i.Stock_Level > i.Low_level 
                    AND i.Availability = 'Available'
                    ORDER BY p.Price DESC
                    LIMIT %s
                """, (*purchased_categories, customer_id, limit))
                recommended_products.extend(cursor.fetchall())
            
            # Get products related to service requests
            if len(recommended_products) < limit:
                cursor.execute("""
                    SELECT DISTINCT p.ProductID, p.Product_Name, p.Category, p.Price,
                           p.Stock, i.Stock_Level, i.Availability,
                           'Related to your service requests' as recommendation_reason
                    FROM service_request sr
                    JOIN PRODUCT p ON (
                        p.Product_Name LIKE CONCAT('%', SUBSTRING_INDEX(sr.problem_description, ' ', 1), '%')
                        OR p.Category LIKE CONCAT('%', sr.service_type, '%')
                    )
                    JOIN INVENTORY i ON p.ProductID = i.ProductID
                    WHERE sr.customer_id = %s
                    AND p.ProductID NOT IN (
                        SELECT oi.ProductID
                        FROM ORDER_ITEM oi
                        JOIN ORDERS o ON oi.OrderID = o.OrderID
                        WHERE o.CustomerID = %s
                    )
                    AND i.Stock_Level > i.Low_Stock 
                    AND i.Availability = 'Available'
                    LIMIT %s
                """, (customer_id, customer_id, limit - len(recommended_products)))
                recommended_products.extend(cursor.fetchall())
        
        # Fill remaining slots with popular/featured products
        if len(recommended_products) < limit:
            cursor.execute("""
                SELECT p.ProductID, p.Product_Name, p.Category, p.Price,
                       p.Stock, i.Stock_Level, i.Availability,
                       'Popular choice' as recommendation_reason
                FROM PRODUCT p
                JOIN INVENTORY i ON p.ProductID = i.ProductID
                WHERE i.Stock_Level > i.Low_Stock 
                AND i.Availability = 'Available'
                ORDER BY p.Price DESC, i.Stock_Level DESC
                LIMIT %s
            """, (limit - len(recommended_products),))
            recommended_products.extend(cursor.fetchall())
        
        # Remove duplicates while preserving order
        seen = set()
        unique_products = []
        for product in recommended_products:
            if product['ProductID'] not in seen:
                seen.add(product['ProductID'])
                unique_products.append(product)
        
        return unique_products[:limit]
        
    except Error as e:
        print(f"Error getting recommendations: {e}")
        return []
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()


# ======================================================
# AI & ROUTES 
# ======================================================

@app.route('/recommended_products', methods=['GET'])
def recommended_products():
    customer_id = request.args.get('customer_id', default=None, type=int)
    products = get_recommended_for_you(customer_id)
    return jsonify(products)

@app.route('/chat', methods=['POST'])
def chat():
    user_message = request.json['message']
    customer_id = request.json.get('customer_id', 1)  # optional, defaults to 1 for testing

    rec_keywords = ['recommend', 'suggestion', 'need', 'buy', 'looking for', 'want']
    is_recommendation = any(keyword in user_message.lower() for keyword in rec_keywords)
    context = ""
    if is_recommendation:
        products = get_available_products()
        if products:
            context += "\n\nAvailable products in our inventory:\n"
            for p in products[:10]:
                context += f"- {p['Product_Name']} ({p['Category']}) - ₱{p['Price']}\n"

    prompt = (
        "You are SpeegoPal, a friendly, knowledgeable e-bike assistant for Speego Cycle. "
        "Provide concise, complete, and helpful answers. "
        "When recommending products, ONLY suggest items from the available inventory provided. "
        "Always include prices in PHP (₱) when discussing products. "
        "Be professional but approachable. Keep answers under 100 words unless necessary."
        f"{context}\n\n"
        f"User: {user_message}\nSpeegoPal:"
    )

    try:
        # === Send request to Llama3 model ===
        response = requests.post(
            'http://localhost:11434/api/generate',
            json={
                "model": "llama3",
                "prompt": prompt,
                "stream": False,
                "options": {
                    "temperature": 0.6,
                    "num_predict": 200,
                    "top_p": 0.9,
                    "stop": ["User:"],
                }
            }
        )
        reply = response.json().get('response', '').strip()

        # === Handle product recommendations ===
        recommendations = []
        if is_recommendation:
            words = user_message.lower().split()
            for word in words:
                if len(word) > 3:
                    recs = get_product_recommendations(word)
                    recommendations.extend(recs)
            seen, unique = set(), []
            for rec in recommendations:
                if rec['ProductID'] not in seen:
                    seen.add(rec['ProductID'])
                    unique.append(rec)
            recommendations = unique[:5]

        # === Save chatbot message to speego_pal table ===
        try:
            conn = get_db_connection()
            if conn:
                cursor = conn.cursor()
                insert_ai = """
                    INSERT INTO speego_pal (service_request_id, problem_description, ai_diagnosis, confidence_score)
                    VALUES (%s, %s, %s, %s)
                """
                cursor.execute(insert_ai, (
                    None, user_message, reply, None
                ))
                conn.commit()
        except Exception as e:
            print("Error saving chat to speego_pal:", e)
        finally:
            if conn and conn.is_connected():
                cursor.close()
                conn.close()

        return jsonify({'reply': reply, 'recommendations': recommendations})

    except Exception as e:
        print("Error in /chat:", e)
        return jsonify({'reply': 'Sorry, there was an error connecting to the AI model.'}), 500



# ======================================================
# DIAGNOSIS - AI does all reasoning here
# ======================================================

@app.route('/diagnose', methods=['POST'])
def diagnose():
    problem_description = request.json['description']
    service_type = request.json.get('service_type', 'Repair')
    product_id = request.json.get('product_id')
    purchase_date = request.json.get('purchase_date')

    warranty_info = None
    is_warranty_covered = False
    if product_id and purchase_date:
        warranty_info = check_warranty_status(product_id, purchase_date)
        is_warranty_covered = warranty_info and warranty_info.get('is_active', False)

    # Use hardcoded service costs if database query fails
    service_costs = get_service_costs()
    if not service_costs:
        service_costs = {
            'Repair': {'EstimatedCost': 800.00},
            'Battery Replacement': {'EstimatedCost': 500.00},
            'Parts Installation': {'EstimatedCost': 300.00}
        }
    
    cost_context = "\n\nActual service costs from our database:\n"
    for stype, cost_data in service_costs.items():
        estimated = cost_data.get('EstimatedCost', 'N/A')
        cost_context += f"- {stype}: ₱{estimated}\n"

    # Get relevant parts from inventory for parts cost estimation
    parts_context = "\n\nAvailable parts and prices:\n"
    parts_context += "- Tire: ₱1,000\n- Battery: ₱4,000\n- Shock: ₱400\n- Tail light: ₱800\n"
    parts_context += "- Controller: ₱4,000\n- Side mirror: ₱500\n- Handlebar: ₱800\n- Motor: ₱7,000\n"
    parts_context += "- Charging Port: ₱200\n- Charger: ₱1,250\n"

    warranty_context = ""
    if is_warranty_covered:
        warranty_context = (
            "\n\nIMPORTANT: This product is under active warranty. "
            "Covered repairs should have NO COST (₱0 or Free)."
        )

    prompt = (
        "You are SpeegoPal, an AI e-bike service assistant for Speego Cycle. "
        "Based on the problem description, identify the most likely service type needed, "
        "estimate which parts might be needed, and calculate the total cost."
        f"{cost_context}{parts_context}{warranty_context}\n"
        "Provide your response in this exact format:\n"
        "Diagnosis: <short 1-2 sentence diagnosis>\n"
        "Service Type: <matching service type from database>\n"
        "Estimated Cost: <labor cost + parts cost = total in ₱>\n"
        "Estimated Time: <approximate repair time>\n\n"
        f"Problem description: {problem_description}\nSpeegoPal:"
    )

    try:
        response = requests.post(
            'http://localhost:11434/api/generate',
            json={"model": "llama3", "prompt": prompt, "stream": False,
                  "options": {"temperature": 0.5, "num_predict": 200}}
        )
        ai_response = response.json().get('response', '').strip()
        diagnosis, detected_service_type, cost, time = "", "", "N/A", "N/A"
        for line in ai_response.splitlines():
            low = line.lower()
            if low.startswith("diagnosis:"): diagnosis = line.split(":", 1)[1].strip()
            elif low.startswith("service type:"): detected_service_type = line.split(":", 1)[1].strip()
            elif low.startswith("estimated cost:"): cost = line.split(":", 1)[1].strip()
            elif low.startswith("estimated time:"): time = line.split(":", 1)[1].strip()

        related_products = get_product_recommendations(problem_description)
        # Normalize cost symbol to PHP
        if cost:
            cost = cost.replace("$", "₱").replace("USD", "₱").replace("US$", "₱")
            # remove duplicates (₱₱)
            cost = cost.replace("₱₱", "₱").strip()

        return jsonify({
            'diagnosis': diagnosis or ai_response,
            'service_type': detected_service_type,
            'cost': cost,
            'time': time,
            'related_products': related_products[:3]
        })
    except Exception as e:
        print("Error in /diagnose:", e)
        return jsonify({'diagnosis': 'Error generating diagnosis'}), 500


# ======================================================
# Save confirmed service request + AI diagnosis
# ======================================================

@app.route('/api/service-request', methods=['POST'])
def create_service_request():
    data = request.json
    customer_id = data.get('customer_id')
    admin_id = data.get('admin_id')
    service_type = data.get('service_type')
    problem_description = data.get('problem_description')
    appointment_date = data.get('appointment_date')
    ai_diagnosis = data.get('ai_diagnosis')
    confidence_score = data.get('confidence_score')
    product_id = data.get('product_id')

    conn = get_db_connection()
    if not conn:
        return jsonify({'error': 'Database connection failed'}), 500

    try:
        cursor = conn.cursor()

        # Insert service request - using lowercase column names with underscores
        insert_sr = """
            INSERT INTO service_request 
            (customer_id, admin_id, service_type, product_id, problem_description, 
             appointment_date, ai_diagnosis, confidence_score, status)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
        """
        cursor.execute(insert_sr, (
            customer_id, admin_id, service_type, product_id, problem_description, 
            appointment_date, ai_diagnosis, confidence_score, "pending"
        ))
        conn.commit()
        service_request_id = cursor.lastrowid

        # Save AI diagnosis into speego_pal
        insert_ai = """
            INSERT INTO speego_pal 
            (service_request_id, problem_description, ai_diagnosis, confidence_score)
            VALUES (%s, %s, %s, %s)
        """
        cursor.execute(insert_ai, (
            service_request_id, problem_description, ai_diagnosis, confidence_score
        ))
        conn.commit()

        return jsonify({
            'message': 'Service request and AI diagnosis saved successfully',
            'service_request_id': service_request_id
        }), 200
    except Error as e:
        conn.rollback()
        print("Error saving service request:", e)
        return jsonify({'error': str(e)}), 500
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()

if __name__ == '__main__':
    app.run(debug=True)