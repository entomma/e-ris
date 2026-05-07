import pdfplumber
import pymysql
import sys
import re
from collections import defaultdict

# ==============================
# Command-line arguments
# ==============================
if len(sys.argv) < 3:
    print("Usage: python extract_courses.py <pdf_path> <student_id>")
    sys.exit(1)

pdf_path = sys.argv[1]
student_id = sys.argv[2]

# ==============================
# Database connection
# ==============================
conn = pymysql.connect(
    host="localhost",
    user="root",
    password="",  # change if you have a MySQL password
    database="eris_db",  # your DB name
    charset="utf8mb4",
    cursorclass=pymysql.cursors.DictCursor
)
cursor = conn.cursor()

# ==============================
# All possible course codes
# ==============================
all_course_codes = [
    'FILIPINO 113', 'HUM 113A', 'CSS 113', 'IT 113', 'CC 113(A)', 'CC 113(B)',
    'MATH 113', 'SOC SCI 113A', 'PATHFit 112', 'NSTP 1', 'FILIPINO 123',
    'MATH 123', 'IT 123', 'NATSCI 123', 'CC 123(C)', 'CSS 123', 'HUM 123',
    'PATHFit 122', 'NSTP 2', 'ENGLISH 213', 'CC 213(D)', 'MULTI 213',
    'CC 213(E)', 'ETHICS 213', 'ITELEC 1', 'SOCSCI 213', 'PATHFit 212',
    'MOBDEV 223', 'SOCSCI 223', 'CPC 223(A)', 'IT 223', 'SAD 223', 'CPC 223(B)',
    'ITMATH 223', 'PATHFit 222', 'SOCSCI 313', 'NET 313', 'CC 313(F)',
    'IAS 313', 'IPT 313', 'ITELEC 3', 'ITELEC 2', 'IAS 323', 'ITELEC 4',
    'HCI 323', 'MS 323', 'CIS 323', 'SIA 323', 'CAP 323', 'SAM 413',
    'CLDCOMP 413', 'ASIA 413', 'CAP 413', 'OJT'
]

# ==============================
# Helper functions
# ==============================
def clean_semester_name(text):
    text = text.upper()
    text = re.sub(r'\bUNITS\b', '', text)
    return text.title().strip()

def parse_transcript(pdf_path):
    semesters = defaultdict(list)
    with pdfplumber.open(pdf_path) as pdf:
        for page in pdf.pages:
            words = page.extract_words()
            if not words:
                continue

            x_values = [w['x0'] for w in words]
            x_threshold = (max(x_values) + min(x_values)) / 2

            rows = defaultdict(list)
            for w in words:
                rows[int(w['top'] // 5)].append(w)

            current_semester_left = "Unknown"
            current_semester_right = "Unknown"

            for row_key in sorted(rows.keys()):
                row_words = rows[row_key]
                left = [w['text'] for w in row_words if w['x0'] < x_threshold]
                right = [w['text'] for w in row_words if w['x0'] >= x_threshold]

                for col_words, side in [(left, "left"), (right, "right")]:
                    if not col_words:
                        continue
                    line_text = " ".join(col_words)
                    line_upper = line_text.upper()

                    if "YEAR" in line_upper and "SEMESTER" in line_upper:
                        if side == "left":
                            current_semester_left = clean_semester_name(line_text)
                        else:
                            current_semester_right = clean_semester_name(line_text)
                        continue

                    for course_code in all_course_codes:
                        if course_code in line_text:
                            grade_status = "Not Enrolled"
                            if "DRP" in line_upper:
                                grade_status = "Dropped"
                            elif re.search(r'\b\d\.\d{1,2}\b', line_text):
                                grade_status = "Passed"
                            
                            semesters[current_semester_left if side == "left" else current_semester_right].append({
                                "course_code": course_code,
                                "status": grade_status
                            })
                            break
    return semesters

# ==============================
# Parse and insert into DB
# ==============================
semesters_data = parse_transcript(pdf_path)
inserted = 0

for semester, courses in semesters_data.items():
    for course in courses:
        cursor.execute("SELECT course_id FROM courses WHERE course_code = %s", (course["course_code"],))
        result = cursor.fetchone()
        if result:
            course_id = result["course_id"]
            cursor.execute("""
                INSERT INTO student_subjects (student_id, course_id, status)
                VALUES (%s, %s, %s)
                ON DUPLICATE KEY UPDATE status = VALUES(status)
            """, (student_id, course_id, course["status"]))
            conn.commit()
            inserted += 1

cursor.close()
conn.close()
print(f"✅ Successfully inserted {inserted} subjects for student {student_id}.")
