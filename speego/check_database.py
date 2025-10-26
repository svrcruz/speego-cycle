"""
Run this script to check your actual database structure
Save as: check_database.py
Run: python check_database.py
"""

import mysql.connector
from mysql.connector import Error

DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': 'Password1$',
    'database': 'speegotest'
}

def check_database_structure():
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor(dictionary=True)
        
        print("="*60)
        print("DATABASE STRUCTURE CHECK")
        print("="*60)
        
        # Check all tables
        cursor.execute("SHOW TABLES")
        tables = cursor.fetchall()
        print("\n📋 TABLES IN DATABASE:")
        for table in tables:
            table_name = list(table.values())[0]
            print(f"  ✓ {table_name}")
        
        # Check PRODUCT table columns
        print("\n📦 PRODUCT TABLE COLUMNS:")
        cursor.execute("DESCRIBE product")
        for col in cursor.fetchall():
            print(f"  - {col['Field']}: {col['Type']}")
        
        # Check INVENTORY table columns
        print("\n📊 INVENTORY TABLE COLUMNS:")
        cursor.execute("DESCRIBE inventory")
        inventory_cols = cursor.fetchall()
        for col in inventory_cols:
            print(f"  - {col['Field']}: {col['Type']}")
        
        # Check ORDER_ITEMS vs order_items
        print("\n🛒 ORDER TABLE NAME CHECK:")
        cursor.execute("SHOW TABLES LIKE '%order%'")
        order_tables = cursor.fetchall()
        for table in order_tables:
            table_name = list(table.values())[0]
            print(f"  ✓ Found: {table_name}")
        
        # Sample data from inventory
        print("\n📈 SAMPLE INVENTORY DATA (first 3 rows):")
        cursor.execute("SELECT * FROM inventory LIMIT 3")
        for row in cursor.fetchall():
            print(f"  ProductID: {row['ProductID']}, Stock_Level: {row['Stock_Level']}, Low_level: {row['Low_level']}, Availability: {row['Availability']}")
        
        # Check if any orders exist
        print("\n🛍️ ORDERS CHECK:")
        cursor.execute("SELECT COUNT(*) as count FROM orders")
        order_count = cursor.fetchone()
        print(f"  Total orders: {order_count['count']}")
        
        cursor.execute("SELECT COUNT(*) as count FROM order_items")
        item_count = cursor.fetchone()
        print(f"  Total order items: {item_count['count']}")
        
        # Check customers
        print("\n👥 CUSTOMERS CHECK:")
        cursor.execute("SELECT CustomerID, Customer_FName, Customer_LName FROM customer")
        for customer in cursor.fetchall():
            print(f"  ID: {customer['CustomerID']} - {customer['Customer_FName']} {customer['Customer_LName']}")
        
        # Check what customer 1 has purchased
        print("\n🛒 CUSTOMER 1 PURCHASE HISTORY:")
        cursor.execute("""
            SELECT o.OrderID, p.Product_Name, p.Category, oi.Quantity
            FROM orders o
            JOIN order_items oi ON o.OrderID = oi.OrderID
            JOIN product p ON oi.ProductID = p.ProductID
            WHERE o.CustomerID = 1
        """)
        purchases = cursor.fetchall()
        if purchases:
            for purchase in purchases:
                print(f"  Order #{purchase['OrderID']}: {purchase['Product_Name']} ({purchase['Category']}) x{purchase['Quantity']}")
        else:
            print("  No purchases found for customer 1")
        
        print("\n" + "="*60)
        print("✅ DATABASE CHECK COMPLETE")
        print("="*60)
        
    except Error as e:
        print(f"❌ Database Error: {e}")
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()

if __name__ == '__main__':
    check_database_structure()