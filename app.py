from flask import Flask, request, jsonify
from flask_cors import CORS
import requests
import json
import mysql.connector
from mysql.connector import Error
from datetime import datetime, timedelta

app = Flask(__name__)
CORS(app)

# -------------------------------
# DATABASE CONFIGURATION
# -------------------------------
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'speegotest'
}

def get_db_connection():
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except Error as e:
        print(f"Database connection error: {e}")
        return None

# -------------------------------
# WARRANTY & PRODUCT UTILITIES
# -------------------------------
def check_warranty_status(product_id, purchase_date):
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
            purchase_dt = datetime.strptime(purchase_date, '%m-%d-%Y')
            warranty_end = purchase_dt + timedelta(days=365)
            today = datetime.now()
            is_active = today <= warranty_end
            days_remaining = (warranty_end - today).days if is_active else 0
            return {
                'has_warranty': False,
                'is_active': is_active,
                'warranty_end_date': warranty_end.strftime('%m-%d-%Y'),
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
            'warranty_end_date': warranty_end.strftime('%m-%d-%Y'),
            'warranty_start_date': warranty['Warranty_StartDate'].strftime('%m-%d-%Y'),
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
    conn = get_db_connection()
    if not conn:
        return {}
    try:
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT ServiceType, EstimatedCost FROM SERVICE_COST")
        costs = cursor.fetchall()
        return {c['ServiceType']: c for c in costs}
    except Error as e:
        print(f"Error fetching service costs: {e}")
        return {}
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()

def get_product_recommendations(keywords):
    conn = get_db_connection()
    if not conn:
        return []
    try:
        cursor = conn.cursor(dictionary=True)
        keyword_pattern = f"%{keywords}%"
        cursor.execute("""
            SELECT p.ProductID, p.Product_Name, p.Category, p.Price
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

# -------------------------------
# SMART DIAGNOSIS ENDPOINT
# -------------------------------
@app.route('/diagnose', methods=['POST'])
def diagnose():
    data = request.json
    problem_description = data.get('description', '')
    service_type = data.get('service_type', 'Repair')
    product_id = data.get('product_id')
    purchase_date = data.get('purchase_date')

    # Warranty check
    warranty_info = None
    is_warranty_covered = False
    if product_id and purchase_date:
        warranty_info = check_warranty_status(product_id, purchase_date)
        is_warranty_covered = warranty_info and warranty_info.get('is_active', False)

    # Cost context
    service_costs = get_service_costs()
    cost_context = "\n".join(
        [f"- {stype}: ₱{data['EstimatedCost']}" for stype, data in service_costs.items()]
    )

    warranty_context = ""
    if is_warranty_covered:
        warranty_context = (
            "\n\nIMPORTANT: Product under active warranty. "
            "Covered repairs are FREE unless outside coverage."
        )

    # AI prompt for Ollama
    prompt = (
        f"You are SpeegoPal, an AI service assistant for Speego Cycle.\n"
        f"Analyze the following e-bike problem and give a concise diagnosis, using these cost references:\n"
        f"{cost_context}\n"
        f"{warranty_context}\n"
        "Respond strictly in this format:\n"
        "Diagnosis: <summary>\n"
        "Service Type: <type>\n"
        "Estimated Cost: <₱ amount or FREE>\n"
        "Estimated Time: <e.g., 3 hours>\n\n"
        f"Problem description: {problem_description}\nSpeegoPal:"
    )

    try:
        response = requests.post(
            "http://localhost:11434/api/generate",
            json={
                "model": "llama3",
                "prompt": prompt,
                "stream": False,
                "options": {"temperature": 0.5, "num_predict": 200}
            }
        )

        ai_response = response.json().get('response', '').strip()

        diagnosis, detected_service_type, cost, time = "", "", "N/A", "N/A"
        for line in ai_response.splitlines():
            if line.lower().startswith("diagnosis:"):
                diagnosis = line.split(":", 1)[1].strip()
            elif line.lower().startswith("service type:"):
                detected_service_type = line.split(":", 1)[1].strip()
            elif line.lower().startswith("estimated cost:"):
                cost = line.split(":", 1)[1].strip()
            elif line.lower().startswith("estimated time:"):
                time = line.split(":", 1)[1].strip()

        if is_warranty_covered:
            cost = "FREE (Warranty Covered)"
            warranty_message = f"✓ Under warranty until {warranty_info['warranty_end_date']}"
        else:
            warranty_message = None
            if detected_service_type in service_costs:
                db_cost = service_costs[detected_service_type].get('EstimatedCost')
                if db_cost:
                    cost = f"₱{db_cost}"

        # -------------------------------
        # SAVE TO DATABASE
        # -------------------------------
        conn = get_db_connection()
        cursor = conn.cursor()

        # Save Service Request
        cursor.execute("""
            INSERT INTO SERVICE_REQUEST (CustomerID, ServiceType, ProblemDescription, AppointmentDate, Status)
            VALUES (%s, %s, %s, %s, %s)
        """, (1, detected_service_type or service_type, problem_description, None, 'Pending'))
        service_request_id = cursor.lastrowid

        # Save Diagnosis
        cursor.execute("""
            INSERT INTO SERVICE_DIAGNOSIS (ServiceRequestID, DiagnosisDetails, TechnicianName, FindingsDate)
            VALUES (%s, %s, %s, %s)
        """, (service_request_id, diagnosis, 'SpeegoPal AI', datetime.now()))

        conn.commit()
        cursor.close()
        conn.close()

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
            'service_request_id': service_request_id
        })

    except Exception as e:
        print("Error in /diagnose:", e)
        return jsonify({
            "diagnosis": "Error: Unable to generate diagnosis.",
            "service_type": "N/A",
            "cost": "N/A",
            "time": "N/A",
            "warranty_covered": False,
            "related_products": []
        }), 500

# -------------------------------
# SMART SCHEDULE ENDPOINT
# -------------------------------
@app.route('/smart-schedule', methods=['GET'])
def smart_schedule():
    try:
        today = datetime.now()
        schedule = []
        for i in range(1, 8):
            day = today + timedelta(days=i)
            for hour in [9, 11, 13, 15]:
                slot_time = day.replace(hour=hour, minute=0)
                schedule.append(slot_time.strftime('%Y-%m-%d %I:%M %p'))
        return jsonify({
            "message": "Available Smart Schedule generated successfully",
            "available_slots": schedule[:8]
        })
    except Exception as e:
        print(f"Smart Schedule Error: {e}")
        return jsonify({"error": "Unable to generate smart schedule"}), 500

if __name__ == '__main__':
    app.run(debug=True)
