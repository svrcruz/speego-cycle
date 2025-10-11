from flask import Flask, request, jsonify
from flask_cors import CORS
import requests

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

    # Ollama request with dynamic constraints
    response = requests.post(
        'http://localhost:11434/api/generate',
        json={
            "model": "llama3",
            "prompt": prompt,
            "stream": False,
            "options": {
                "temperature": 0.6,     # Slight creativity, not too stiff
                "num_predict": 150,     # Enough to complete a clear reply
                "top_p": 0.9,           # Balanced randomness
                "stop": ["User:"],      # Prevents extra turns
            }
        }
    )

    reply = response.json().get('response', '').strip()
    return jsonify({'reply': reply})

if __name__ == '__main__':
    app.run(debug=True)
