#!/usr/bin/env python3
import argparse
import requests
import json
import csv
from typing import List, Dict

# Constants
GEMINI_API_URL = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent"

# -----------------------------
# JSON GENERATOR AND CONVERTER
# -----------------------------

def convert_questions_to_tsv(questions: List[Dict[str, object]], file_path: str) -> None:
    rows = [
        ["M=MODULE", "module_enabled", "module_name"],
        ["S=SUBJECT", "subject_enabled", "subject_name", "subject_description"],
        ["Q=QUESTION", "question_enabled", "question_description", "question_explanation", "question_type", "question_difficulty", "question_position", "question_timer", "question_fullscreen", "question_inline_answers", "question_auto_next"],
        ["A=ANSWER", "answer_enabled", "answer_description", "answer_explanation", "answer_isright", "answer_position", "answer_keyboard_key"]
    ]

    module_written = set()
    subject_written = set()
    position = 1

    for q in questions:
        module = q["module"]
        subject = q["subject"]["name"]
        subject_desc = q["subject"]["description"]
        question_text = q["question"]["text"]
        difficulty = q["question"].get("difficulty", 3)
        timer = q["question"].get("timer", 60)
        options = q["question"]["options"]

        if module not in module_written:
            rows.append(["M", "1", module])
            module_written.add(module)

        subject_key = f"{module}:{subject}"
        if subject_key not in subject_written:
            rows.append(["S", "1", subject, subject_desc])
            subject_written.add(subject_key)

        rows.append([
            "Q", "1", question_text, "", "S",
            str(difficulty), str(position), str(timer), "1", "1", "1"
        ])
        position += 1

        for i, opt in enumerate(options):
            rows.append([
                "A", "1", opt["text"], "", "1" if opt.get("correct", False) else "0", str(i + 1), chr(65 + i)
            ])

    with open(file_path, 'w', encoding='utf-8', newline='') as f:
        writer = csv.writer(f, delimiter='\t')
        writer.writerows(rows)
    print(f"✅ TSV generated at: {file_path}")

# -----------------------------
# GEMINI INTEGRATION
# -----------------------------

def prompt_gemini(api_key: str, module_name: str, description: str, subjects: List[str], num_questions: int = 10) -> List[Dict[str, object]]:
    """
    Prompt Gemini to generate structured MCQs in expected format.
    """
    subjects_list = ', '.join(f'"{subject}"' for subject in subjects)
    first_subject = subjects[0] if subjects else ""
    prompt = f"""
You are an exam generator for a TCExam-compatible system.
Generate {num_questions} multiple choice questions under the module "{module_name}".
Each question should belong to one of the following subjects: {subjects_list}.
Respond ONLY with a JSON array(no need to wrap in backticks or include formatting) where each question includes:
- "module": The module name.
- "subject": An object with "name" and "description".
- "question": An object with:
    - "text": The question text.
    - "difficulty": An integer from 1 (easy) to 5 (hard).
    - "timer": Time in seconds to answer the question.
    - "options": A list of options, each with "text" and a boolean "correct" indicating if it's the correct answer.

Example:
[
  {{
    "module": "{module_name}",
    "subject": {{
      "name": "{first_subject}",
      "description": "{description}"
    }},
    "question": {{
      "text": "Your question?",
      "difficulty": 2,
      "timer": 60,
      "options": [
        {{ "text": "Option 1", "correct": true }},
        {{ "text": "Option 2", "correct": false }},
        {{ "text": "Option 3", "correct": false }},
        {{ "text": "Option 4", "correct": false }}
      ]
    }}
  }}
]
Only include one correct answer per question and avoid explanations or text outside the JSON.
    """.strip()

    payload = {
        "contents": [
            {"parts": [{"text": prompt}]}
        ]
    }

    response = requests.post(
        f"{GEMINI_API_URL}?key={api_key}",
        json=payload
    )
    response.raise_for_status()
    content = response.json()

    # Extract text and parse JSON
    text_response = content["candidates"][0]["content"]["parts"][0]["text"]
    try:
        questions = json.loads(text_response)
        return questions
    except json.JSONDecodeError:
        print("❌ Could not decode Gemini response as JSON.")
        print(text_response)
        return []

# -----------------------------
# MAIN EXECUTION
# -----------------------------

def main():
    parser = argparse.ArgumentParser(description="Generate TCExam-compatible TSV from Gemini-generated questions.")
    parser.add_argument("--api_key", required=True, help="Your Gemini API key.")
    parser.add_argument("--module", required=True, help="Module name.")
    parser.add_argument("--description", required=True, help="Description for each subject.")
    parser.add_argument("--subjects", nargs='+', required=True, help="List of subject names.")
    parser.add_argument("--num_questions", type=int, default=10, help="Number of questions to generate.")
    parser.add_argument("--output", default="generated_questions.tsv", help="Output TSV file path.")

    args = parser.parse_args()

    questions = prompt_gemini(
        api_key=args.api_key,
        module_name=args.module,
        description=args.description,
        subjects=args.subjects,
        num_questions=args.num_questions
    )

    if questions:
        convert_questions_to_tsv(questions, args.output)
    else:
        print("❌ No questions were generated.")

if __name__ == "__main__":
    main()
