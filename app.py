from flask import Flask, request, jsonify
from flask_cors import CORS
import requests

app = Flask(__name__)
CORS(app)

@app.route('/chat', methods=['POST'])
def chat():
    user_message = request.json['message']

    # Send message to Ollama
    response = requests.post(
        'http://localhost:11434/api/generate',
        json={
        "model": "tinyllama",
        "prompt": user_message,
        "stream": False,
        "options": {
            "num_predict": 100  # limits response length
            }
        }

    )

    reply = response.json()['response']
    return jsonify({'reply': reply})

if __name__ == '__main__':
    app.run(debug=True)