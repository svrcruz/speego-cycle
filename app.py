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
    'password': '',
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
            # No warranty found, calculate default warranty (e.g., 1 year)
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
        
        # Check if warranty is still active
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
    """Fetch service costs from database"""
    conn = get_db_connection()
    if not conn:
        return {}
    
    try:
        cursor = conn.cursor(dictionary=True)
        cursor.execute("""
            SELECT ServiceType, EstimatedCost, TotalCost 
            FROM SERVICE_COST
        """)
        costs = cursor.fetchall()
        return {c['ServiceType']: c for c in costs}
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
                   i.Stock_level, i.Low_level, i.Availability
            FROM PRODUCT p
            JOIN INVENTORY i ON p.ProductID = i.ProductID
            WHERE i.Stock_level > i.Low_level AND i.Availability = 'Available'
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
                   i.Stock_level, i.Availability
            FROM PRODUCT p
            JOIN INVENTORY i ON p.ProductID = i.ProductID
            WHERE (p.Product_Name LIKE %s OR p.Category LIKE %s)
            AND i.Stock_level > 0 AND i.Availability = 'Available'
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

        # Insert into service_diagnosis table
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

        # Get diagnosis ID
        diagnosis_id = cursor.lastrowid

        # Update related service request status
        update_query = """
            UPDATE service_request
            SET Status = 'Diagnosed'
            WHERE ServiceRequestID = %s
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
            (CustomerID, AdminID, ServiceType, ProblemDescription, AppointmentDate, Status)
            VALUES (%s, %s, %s, %s, %s, %s)
        """

        cursor.execute(insert_query, (
            request_data.get('customer_id', 1),  # Replace with actual logged-in customer later
            request_data.get('admin_id', None),
            request_data.get('service_type', 'Repair'),
            request_data.get('problem_description'),
            None,  # AppointmentDate can be set later via /schedule
            'Pending Diagnosis'
        ))

        conn.commit()
        service_request_id = cursor.lastrowid
        return service_request_id

    except Error as e:
        print(f"Error saving service request: {e}")
        conn.rollback()
        return None
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()

@app.route('/warranty/check', methods=['POST'])
def check_warranty():
    """Check warranty status for a product"""
    data = request.json
    product_id = data.get('product_id')
    purchase_date = data.get('purchase_date')
    
    if not product_id or not purchase_date:
        return jsonify({
            'error': 'Product ID and purchase date are required'
        }), 400
    
    try:
        warranty_info = check_warranty_status(product_id, purchase_date)
        
        if warranty_info is None:
            return jsonify({
                'error': 'Unable to check warranty status'
            }), 500
        
        return jsonify({
            'warranty': warranty_info,
            'message': 'Warranty check completed'
        })
        
    except Exception as e:
        print(f"Error in warranty check: {e}")
        return jsonify({
            'error': 'Error checking warranty'
        }), 500

@app.route('/chat', methods=['POST'])
def chat():
    user_message = request.json['message']
    
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
        
        recommendations = []
        if is_recommendation:
            words = user_message.lower().split()
            for word in words:
                if len(word) > 3:
                    recs = get_product_recommendations(word)
                    recommendations.extend(recs)
            
            seen = set()
            unique_recs = []
            for rec in recommendations:
                if rec['ProductID'] not in seen:
                    seen.add(rec['ProductID'])
                    unique_recs.append(rec)
            recommendations = unique_recs[:5]
        
        return jsonify({
            'reply': reply,
            'recommendations': recommendations
        })

    except Exception as e:
        print("Error in /chat:", e)
        return jsonify({'reply': 'Sorry, there was an error connecting to the AI model.'}), 500

@app.route('/diagnose', methods=['POST'])
def diagnose():
    problem_description = request.json['description']
    service_type = request.json.get('service_type', 'Repair')
    product_id = request.json.get('product_id')
    purchase_date = request.json.get('purchase_date')
    
    # Check warranty status if product details provided
    warranty_info = None
    is_warranty_covered = False
    
    if product_id and purchase_date:
        warranty_info = check_warranty_status(product_id, purchase_date)
        is_warranty_covered = warranty_info and warranty_info.get('is_active', False)
    
    # Get actual service costs from database
    service_costs = get_service_costs()
    
    cost_context = ""
    if service_costs:
        cost_context = "\n\nActual service costs from our database:\n"
        for stype, cost_data in service_costs.items():
            estimated = cost_data.get('EstimatedCost', 'N/A')
            cost_context += f"- {stype}: ₱{estimated}\n"

    warranty_context = ""
    if is_warranty_covered:
        warranty_context = (
            "\n\nIMPORTANT: This product is under active warranty. "
            "Covered repairs should have NO COST (₱0 or Free). "
            "Only recommend parts/services not covered by warranty with their actual costs."
        )

    prompt = (
        "You are SpeegoPal, an AI e-bike service assistant for Speego Cycle. "
        "Based on the problem description, identify the most likely service type needed "
        "and use the ACTUAL costs from our service database."
        f"{cost_context}"
        f"{warranty_context}\n"
        "Provide your response in this exact format:\n"
        "Diagnosis: <short 1-2 sentence diagnosis>\n"
        "Service Type: <matching service type from database>\n"
        "Estimated Cost: <use actual cost from database in PHP, or FREE if warranty covers it>\n"
        "Estimated Time: <approximate repair time>\n\n"
        f"Problem description: {problem_description}\nSpeegoPal:"
    )

    try:
        response = requests.post(
            'http://localhost:11434/api/generate',
            json={
                "model": "llama3",
                "prompt": prompt,
                "stream": False,
                "options": {"temperature": 0.5, "num_predict": 200}
            }
        )

        ai_response = response.json().get('response', '').strip()

        diagnosis = ""
        detected_service_type = ""
        cost = "N/A"
        time = "N/A"

        for line in ai_response.splitlines():
            line_lower = line.lower()
            if line_lower.startswith("diagnosis:"):
                diagnosis = line.split(":", 1)[1].strip()
            elif line_lower.startswith("service type:"):
                detected_service_type = line.split(":", 1)[1].strip()
            elif "cost" in line_lower and line_lower.startswith("estimated cost:"):
                cost = line.split(":", 1)[1].strip()
            elif "time" in line_lower and line_lower.startswith("estimated time:"):
                time = line.split(":", 1)[1].strip()

        # Override cost if warranty is active
        estimated_cost_numeric = 0
        if is_warranty_covered:
            cost = "FREE (Warranty Covered)"
            warranty_message = f"✓ Under warranty until {warranty_info['warranty_end_date']}"
        else:
            warranty_message = None
            # Verify cost against database for non-warranty cases
            if detected_service_type and detected_service_type in service_costs:
                db_cost = service_costs[detected_service_type].get('EstimatedCost')
                if db_cost:
                    cost = f"₱{db_cost}"
                    estimated_cost_numeric = db_cost

        # Save service request to database
        service_request_data = {
            'product_id': product_id,
            'service_type': detected_service_type or service_type,
            'problem_description': problem_description,
            'estimated_cost': estimated_cost_numeric,
            'estimated_time': time
        }
        
        service_request_id = save_service_request_to_db(service_request_data)
        
        # Save diagnosis to database
        if service_request_id:
            diagnosis_data = {
                'service_request_id': service_request_id,
                'diagnosis_details': diagnosis or ai_response,
                'technician_name': 'SpeegoPal AI'
            }
            diagnosis_id = save_diagnosis_to_db(diagnosis_data)
        else:
            diagnosis_id = None

        related_products = get_product_recommendations(problem_description)

        return jsonify({
            'diagnosis': diagnosis or ai_response,
            'service_type': detected_service_type,
            'cost': cost,
            'time': time,
            'warranty': warranty_info,
            'warranty_covered': is_warranty_covered,
            'warranty_message': warranty_message,
            'related_products': related_products[:3],
            'service_request_id': service_request_id,
            'diagnosis_id': diagnosis_id
        })

    except Exception as e:
        print("Error in /diagnose:", e)
        return jsonify({
            "diagnosis": "Error: Unable to generate diagnosis.",
            "service_type": "N/A",
            "cost": "N/A",
            "time": "N/A",
            "warranty_covered": False,
            "related_products": [],
            "service_request_id": None,
            "diagnosis_id": None
        }), 500

@app.route('/products/search', methods=['POST'])
def search_products():
    """Search products by keyword or category"""
    search_term = request.json.get('query', '')
    category = request.json.get('category', None)
    
    if search_term:
        products = get_product_recommendations(search_term)
    else:
        products = get_available_products(category)
    
    return jsonify({'products': products})

@app.route('/products/categories', methods=['GET'])
def get_categories():
    """Get all product categories"""
    conn = get_db_connection()
    if not conn:
        return jsonify({'categories': []})
    
    try:
        cursor = conn.cursor()
        cursor.execute("SELECT DISTINCT Category FROM PRODUCT WHERE Category IS NOT NULL")
        categories = [row[0] for row in cursor.fetchall()]
        return jsonify({'categories': categories})
    except Error as e:
        print(f"Error fetching categories: {e}")
        return jsonify({'categories': []})
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()

@app.route('/smart-schedule', methods=['GET'])
def smart_schedule():
    """Automatically suggest available service slots"""
    try:
        today = datetime.now()
        schedule = []
        
        # Generate next 7 available time slots
        for i in range(1, 8):
            day = today + timedelta(days=i)
            for hour in [9, 11, 13, 15]:  # 9AM, 11AM, 1PM, 3PM
                slot_time = day.replace(hour=hour, minute=0)
                schedule.append(slot_time.strftime('%Y-%m-%d %I:%M %p'))

        return jsonify({
            "message": "Available Smart Schedule generated successfully",
            "available_slots": schedule[:8]  # limit to 8 slots
        })
    except Exception as e:
        print(f"Smart Schedule Error: {e}")
        return jsonify({
            "error": "Unable to generate smart schedule"
        }), 500

@app.route('/schedule', methods=['POST'])
def schedule_appointment():
    """Save appointment schedule to database"""
    data = request.json
    service_request_id = data.get('service_request_id')
    appointment_date = data.get('appointment_date')
    
    if not service_request_id or not appointment_date:
        return jsonify({'error': 'Service request ID and appointment date required'}), 400
    
    conn = get_db_connection()
    if not conn:
        return jsonify({'error': 'Database connection failed'}), 500
    
    try:
        cursor = conn.cursor()
        
        # Update SERVICE_REQUEST with appointment date
        update_query = """
            UPDATE SERVICE_REQUEST 
            SET AppointmentDate = %s
            WHERE ServiceRequestID = %s
        """
        
        # Parse the appointment date string
        appointment_dt = datetime.strptime(appointment_date, '%Y-%m-%d %I:%M %p')
        
        cursor.execute(update_query, (appointment_dt, service_request_id))
        conn.commit()
        
        return jsonify({
            'message': 'Appointment scheduled successfully',
            'service_request_id': service_request_id,
            'appointment_date': appointment_date
        })
        
    except Exception as e:
        print(f"Error scheduling appointment: {e}")
        conn.rollback()
        return jsonify({'error': 'Failed to schedule appointment'}), 500
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()

if __name__ == '__main__':
    app.run(debug=True)