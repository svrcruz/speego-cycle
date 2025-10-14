from flask import Flask, request, jsonify
from flask_cors import CORS
import requests
import json

app = Flask(__name__)
CORS(app)

@app.route('/chat', methods=['POST'])
def chat():
    user_message = request.json['message']

    prompt = (
        "You are SpeegoPal, a friendly, knowledgeable e-bike assistant. "
        "Provide concise, complete, and helpful answers. "
        "Be professional but approachable. Avoid unnecessary details unless asked. "
        "Keep answers under 100 words unless absolutely necessary.\n\n"
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
                    "num_predict": 150,
                    "top_p": 0.9,
                    "stop": ["User:"],
                }
            }
        )

        reply = response.json().get('response', '').strip()
        return jsonify({'reply': reply})

    except Exception as e:
        print("Error in /chat:", e)
        return jsonify({'reply': 'Sorry, there was an error connecting to the AI model.'}), 500


@app.route('/diagnose', methods=['POST'])
def diagnose():
    problem_description = request.json['description']

    prompt = (
        "You are SpeegoPal, an AI e-bike service assistant. "
        "Given the user's description, provide three outputs in this exact format:\n"
        "Diagnosis: <short 1-2 sentence diagnosis>\n"
        "Estimated Cost: <approximate price in PHP>\n"
        "Estimated Time: <approximate repair time in hours or days>\n\n"
        f"Problem description: {problem_description}\nSpeegoPal:"
    )

    try:
        response = requests.post(
            'http://localhost:11434/api/generate',
            json={
                "model": "llama3",
                "prompt": prompt,
                "stream": False,
                "options": {"temperature": 0.6, "num_predict": 150}
            }
        )

        ai_response = response.json().get('response', '').strip()

        # Split response into fields (basic parsing)
        diagnosis = ""
        cost = "N/A"
        time = "N/A"

        for line in ai_response.splitlines():
            if line.lower().startswith("diagnosis:"):
                diagnosis = line.split(":", 1)[1].strip()
            elif "cost" in line.lower():
                cost = line.split(":", 1)[1].strip()
            elif "time" in line.lower():
                time = line.split(":", 1)[1].strip()

        return jsonify({
            'diagnosis': diagnosis or ai_response,
            'cost': cost,
            'time': time
        })

    except Exception as e:
        print("Error in /diagnose:", e)
        return jsonify({
            "diagnosis": "Error: Unable to generate diagnosis.",
            "estimated_cost": "N/A",
            "estimated_time": "N/A"
        }), 500


if __name__ == '__main__':
    app.run(debug=True)
