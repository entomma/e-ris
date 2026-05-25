<?php
session_start();
include('includes/db_connect.php');

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Initialize temporary enrollment session if not exists
if (!isset($_SESSION['temp_enrollments'])) {
    $_SESSION['temp_enrollments'] = [];
}

// ====================================================
// 🔹 Get CURRENT SEMESTER (global for all students)
// ====================================================
function getCurrentSemester($conn) {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'current_semester'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? intval($result['setting_value']) : 1; // Default to 1 if not set
}
// ====================================================
// 🔹 AJAX: Upload Shiftee Form
// ====================================================
if (isset($_POST['action']) && $_POST['action'] === 'upload_shiftee_form') {
    try {
        if (!isset($_FILES['shiftee_form']) || $_FILES['shiftee_form']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Please select a valid shiftee form file");
        }
        
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        $file_type = $_FILES['shiftee_form']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception("Only PDF, JPG, and PNG files are allowed");
        }
        
        // Check file size (max 2MB)
        if ($_FILES['shiftee_form']['size'] > 2 * 1024 * 1024) {
            throw new Exception("File size must be less than 2MB");
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = 'uploads/evaluations/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($_FILES['shiftee_form']['name'], PATHINFO_EXTENSION);
        $filename = 'shiftee_' . $student_id . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($_FILES['shiftee_form']['tmp_name'], $file_path)) {
            throw new Exception("Failed to upload shiftee form");
        }
        
        // Check if shiftee form already exists for this student
        $stmt = $conn->prepare("SELECT file_id FROM files WHERE student_id = ? AND shiftee_form IS NOT NULL");
        $stmt->execute([$student_id]);
        $existing_shiftee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_shiftee) {
            // Update existing shiftee form - delete old file first
            $stmt = $conn->prepare("SELECT shiftee_form FROM files WHERE file_id = ?");
            $stmt->execute([$existing_shiftee['file_id']]);
            $old_file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($old_file['shiftee_form'] && file_exists($old_file['shiftee_form'])) {
                unlink($old_file['shiftee_form']);
            }
            
            // Update with new shiftee form
            $stmt = $conn->prepare("UPDATE files SET shiftee_form = ? WHERE file_id = ?");
            $stmt->execute([$file_path, $existing_shiftee['file_id']]);
        } else {
            // Insert new shiftee form record
            $stmt = $conn->prepare("
                INSERT INTO files (student_id, shiftee_form, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$student_id, $file_path]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Shiftee form uploaded successfully!', 'file_path' => $file_path]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ====================================================
// 🔹 AJAX: Upload Letter of Intent
// ====================================================
if (isset($_POST['action']) && $_POST['action'] === 'upload_loi_form') {
    try {
        if (!isset($_FILES['loi_form']) || $_FILES['loi_form']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Please select a valid letter of intent file");
        }
        
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        $file_type = $_FILES['loi_form']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception("Only PDF, JPG, and PNG files are allowed");
        }
        
        // Check file size (max 2MB)
        if ($_FILES['loi_form']['size'] > 2 * 1024 * 1024) {
            throw new Exception("File size must be less than 2MB");
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = 'uploads/evaluations/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($_FILES['loi_form']['name'], PATHINFO_EXTENSION);
        $filename = 'loi_' . $student_id . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($_FILES['loi_form']['tmp_name'], $file_path)) {
            throw new Exception("Failed to upload letter of intent");
        }
        
        // Check if letter of intent already exists for this student
        $stmt = $conn->prepare("SELECT file_id FROM files WHERE student_id = ? AND loi_form IS NOT NULL");
        $stmt->execute([$student_id]);
        $existing_loi = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_loi) {
            // Update existing letter of intent - delete old file first
            $stmt = $conn->prepare("SELECT loi_form FROM files WHERE file_id = ?");
            $stmt->execute([$existing_loi['file_id']]);
            $old_file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($old_file['loi_form'] && file_exists($old_file['loi_form'])) {
                unlink($old_file['loi_form']);
            }
            
            // Update with new letter of intent
            $stmt = $conn->prepare("UPDATE files SET loi_form = ? WHERE file_id = ?");
            $stmt->execute([$file_path, $existing_loi['file_id']]);
        } else {
            // Insert new letter of intent record
            $stmt = $conn->prepare("
                INSERT INTO files (student_id, loi_form, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$student_id, $file_path]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Letter of intent uploaded successfully!', 'file_path' => $file_path]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ====================================================
// 🔹 AJAX: Get current Shiftee Form
// ====================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_shiftee_form') {
    $stmt = $conn->prepare("SELECT shiftee_form FROM files WHERE student_id = ? AND shiftee_form IS NOT NULL");
    $stmt->execute([$student_id]);
    $shiftee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['shiftee_path' => $shiftee ? $shiftee['shiftee_form'] : null]);
    exit();
}

// ====================================================
// 🔹 AJAX: Get current Letter of Intent
// ====================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_loi_form') {
    $stmt = $conn->prepare("SELECT loi_form FROM files WHERE student_id = ? AND loi_form IS NOT NULL");
    $stmt->execute([$student_id]);
    $loi = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['loi_path' => $loi ? $loi['loi_form'] : null]);
    exit();
}
// ====================================================
// 🔹 AJAX: Get smart schedule recommendation
// ====================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_recommendation') {
    $current_semester = getCurrentSemester($conn);

    // Get already temp-enrolled and DB-enrolled course IDs
    $temp_course_ids = [];
    foreach ($_SESSION['temp_enrollments'] as $te) {
        $stmt = $conn->prepare("SELECT course_id FROM section_courses WHERE section_course_id = ?");
        $stmt->execute([$te['section_course_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $temp_course_ids[] = $row['course_id'];
    }

    // Get available courses (same logic as fetch_available_subjects)
    $exclude_clause = empty($temp_course_ids)
        ? ""
        : "AND c.course_id NOT IN (" . implode(',', array_fill(0, count($temp_course_ids), '?')) . ")";

    $sql = "SELECT DISTINCT c.course_id, c.course_code, c.course_title, c.year_level, c.units
            FROM courses c
            WHERE c.semester = ?
            AND c.course_id NOT IN (
                SELECT course_id FROM student_subjects
                WHERE student_id = ? AND status IN ('passed','failed','enrolled')
            )
            $exclude_clause
            ORDER BY c.year_level, c.course_code";

    $params = [$current_semester, $student_id];
    if (!empty($temp_course_ids)) $params = array_merge($params, $temp_course_ids);

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check prerequisites
    $passed_stmt = $conn->prepare("SELECT course_id FROM student_subjects WHERE student_id = ? AND status = 'passed'");
    $passed_stmt->execute([$student_id]);
    $passed_courses = $passed_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Track which schedule slots are taken (day+time combos)
    $booked_slots = []; // array of [day, start_time, end_time]

    // Load already-enrolled slots from DB
    $enrolled_slots = $conn->prepare("
        SELECT scs.day_of_week, scs.start_time, scs.end_time
        FROM student_enrollments se
        JOIN section_courses sc ON se.section_course_id = sc.section_course_id
        JOIN section_course_schedules scs ON sc.section_course_id = scs.section_course_id
        WHERE se.student_id = ?
    ");
    $enrolled_slots->execute([$student_id]);
    foreach ($enrolled_slots->fetchAll(PDO::FETCH_ASSOC) as $slot) {
        $booked_slots[] = $slot;
    }

    // Load temp enrollment slots
    foreach ($_SESSION['temp_enrollments'] as $te) {
        $te_slots = $conn->prepare("
            SELECT day_of_week, start_time, end_time
            FROM section_course_schedules WHERE section_course_id = ?
        ");
        $te_slots->execute([$te['section_course_id']]);
        foreach ($te_slots->fetchAll(PDO::FETCH_ASSOC) as $slot) {
            $booked_slots[] = $slot;
        }
    }

    function timesOverlap($s1, $e1, $s2, $e2) {
        return ($s1 < $e2) && ($e1 > $s2);
    }

    function slotConflicts($newSlots, $bookedSlots) {
        foreach ($newSlots as $ns) {
            foreach ($bookedSlots as $bs) {
                if ($ns['day_of_week'] === $bs['day_of_week']) {
                    if (timesOverlap($ns['start_time'], $ns['end_time'], $bs['start_time'], $bs['end_time'])) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    $recommendation = [];

    foreach ($courses as $course) {
        // Check prerequisites
        $prereq_stmt = $conn->prepare("SELECT prerequisite_course_id FROM course_prerequisites WHERE course_id = ?");
        $prereq_stmt->execute([$course['course_id']]);
        $prereqs = $prereq_stmt->fetchAll(PDO::FETCH_COLUMN);
        $prereqs_met = true;
        foreach ($prereqs as $pre) {
            if (!in_array($pre, $passed_courses)) { $prereqs_met = false; break; }
        }
        if (!$prereqs_met) continue;

        // Get sections with available slots
        $sec_stmt = $conn->prepare("
            SELECT sc.section_course_id, s.section_name, sc.max_capacity, sc.current_enrollment,
                   (sc.max_capacity - sc.current_enrollment) as available_slots
            FROM section_courses sc
            JOIN sections s ON sc.section_id = s.section_id
            WHERE sc.course_id = ? AND sc.semester = ?
            HAVING available_slots > 0
        ");
        $sec_stmt->execute([$course['course_id'], $current_semester]);
        $sections = $sec_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sections as $section) {
            // Get schedules for this section
            $sched_stmt = $conn->prepare("
                SELECT day_of_week, start_time, end_time,
                       CONCAT(day_of_week, ' ', TIME_FORMAT(start_time,'%h:%i %p'), '-', TIME_FORMAT(end_time,'%h:%i %p')) as label
                FROM section_course_schedules WHERE section_course_id = ?
            ");
            $sched_stmt->execute([$section['section_course_id']]);
            $section_slots = $sched_stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($section_slots)) continue;

            if (!slotConflicts($section_slots, $booked_slots)) {
                // This section works — add it to recommendation
                $schedule_label = implode(', ', array_column($section_slots, 'label'));
                $recommendation[] = [
                    'section_course_id' => $section['section_course_id'],
                    'course_code'       => $course['course_code'],
                    'course_title'      => $course['course_title'],
                    'section_name'      => $section['section_name'],
                    'units'             => $course['units'],
                    'schedule'          => $schedule_label,
                    'available_slots'   => $section['available_slots'],
                ];
                // Mark these slots as booked so next course won't conflict
                foreach ($section_slots as $slot) $booked_slots[] = $slot;
                break; // move to next course
            }
        }
    }

    echo json_encode(['success' => true, 'recommendation' => $recommendation]);
    exit();
}
// ====================================================
// 🔹 AJAX: Get current semester only
// ====================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_current_semester') {
    $current_semester = getCurrentSemester($conn);
    echo json_encode(['current_semester' => $current_semester]);
    exit();
}
// ====================================================
// 🔹 AJAX: Upload signature - FIXED VERSION
// ====================================================
if (isset($_POST['action']) && $_POST['action'] === 'upload_signature') {
    try {
        if (!isset($_FILES['signature']) || $_FILES['signature']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Please select a valid signature file");
        }
        
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $file_type = $_FILES['signature']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception("Only JPG, PNG, and GIF files are allowed");
        }
        
        // Check file size (max 2MB)
        if ($_FILES['signature']['size'] > 2 * 1024 * 1024) {
            throw new Exception("File size must be less than 2MB");
        }
        
        // Create signatures directory if it doesn't exist
        $upload_dir = 'uploads/signatures/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION);
        $filename = 'signature_' . $student_id . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($_FILES['signature']['tmp_name'], $file_path)) {
            throw new Exception("Failed to upload signature");
        }
        
        // FIXED: Check if ANY file record exists for this student (regardless of signature_form value)
        $stmt = $conn->prepare("SELECT file_id, signature_form FROM files WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $existing_file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_file) {
            // Update existing record - delete old signature file if it exists
            if ($existing_file['signature_form'] && file_exists($existing_file['signature_form'])) {
                unlink($existing_file['signature_form']);
            }
            
            // Update with new signature
            $stmt = $conn->prepare("UPDATE files SET signature_form = ? WHERE file_id = ?");
            $stmt->execute([$file_path, $existing_file['file_id']]);
            $action = 'updated';
        } else {
            // Insert new file record
            $stmt = $conn->prepare("
                INSERT INTO files (student_id, signature_form, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$student_id, $file_path]);
            $action = 'created';
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Signature uploaded successfully!',
            'action' => $action
        ]);
        
    } catch (Exception $e) {
        // Clean up uploaded file if there was an error after moving it
        if (isset($file_path) && file_exists($file_path)) {
            unlink($file_path);
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}
// ====================================================
// 🔹 AJAX: Get current signature - FIXED VERSION
// ====================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_signature') {
    $stmt = $conn->prepare("SELECT signature_form FROM files WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'signature_path' => $file ? $file['signature_form'] : null,
        'has_record' => $file ? true : false
    ]);
    exit();
}
// ====================================================
// 🔹 AJAX: Submit enrollment form (FINAL SUBMISSION)
// ====================================================
if (isset($_POST['action']) && $_POST['action'] === 'submit_enrollment') {
    try {
        $conn->beginTransaction();
        
        // Check if student already has a pending submission for current semester
        $current_semester = getCurrentSemester($conn);
        $stmt = $conn->prepare("
            SELECT submission_id FROM submissions 
            WHERE student_id = ? AND status = 'pending' AND type = 'enrollment'
        ");
        $stmt->execute([$student_id]);
        
        if ($stmt->fetch()) {
            throw new Exception("You already have a pending enrollment submission");
        }
        
        // Check if there are any temporary enrollments
        if (empty($_SESSION['temp_enrollments'])) {
            throw new Exception("No subjects selected for enrollment");
        }
        
        // Generate reference ID
        $reference_id = 'ENR-' . date('Ymd-His') . '-' . $student_id;
        
        // Create submission record
        $stmt = $conn->prepare("
            INSERT INTO submissions (student_id, reference_id, type, status, created_at) 
            VALUES (?, ?, 'enrollment', 'pending', NOW())
        ");
        $stmt->execute([$student_id, $reference_id]);
        $submission_id = $conn->lastInsertId();
        
        // Process all temporary enrollments and save to database
        foreach ($_SESSION['temp_enrollments'] as $temp_enrollment) {
            $section_course_id = $temp_enrollment['section_course_id'];
            
            // Check capacity
            $stmt = $conn->prepare("SELECT max_capacity, current_enrollment FROM section_courses WHERE section_course_id = ?");
            $stmt->execute([$section_course_id]);
            $section = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($section['current_enrollment'] >= $section['max_capacity']) {
                throw new Exception("Section " . $temp_enrollment['section_name'] . " is now full");
            }
            
            // Check conflicts with existing database enrollments
            $stmt = $conn->prepare("
                SELECT 
                    c.course_code as conflicting_course,
                    s.section_name as conflicting_section
                FROM student_enrollments se
                JOIN section_courses sc ON se.section_course_id = sc.section_course_id
                JOIN section_course_schedules scs ON sc.section_course_id = scs.section_course_id
                JOIN courses c ON sc.course_id = c.course_id
                JOIN sections s ON sc.section_id = s.section_id
                WHERE se.student_id = ?
                AND EXISTS (
                    SELECT 1 FROM section_course_schedules new_scs
                    WHERE new_scs.section_course_id = ?
                    AND new_scs.day_of_week = scs.day_of_week
                    AND (
                        (new_scs.start_time BETWEEN scs.start_time AND DATE_SUB(scs.end_time, INTERVAL 1 MINUTE)) OR
                        (new_scs.end_time BETWEEN DATE_ADD(scs.start_time, INTERVAL 1 MINUTE) AND scs.end_time) OR
                        (new_scs.start_time <= scs.start_time AND new_scs.end_time >= scs.end_time)
                    )
                )
            ");
            $stmt->execute([$student_id, $section_course_id]);
            if ($conflict = $stmt->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception("Schedule conflict with " . $conflict['conflicting_course'] . " (" . $conflict['conflicting_section'] . ")");
            }
            
            // Enroll student - THIS IS WHERE DATABASE INSERTION HAPPENS
            $stmt = $conn->prepare("INSERT INTO student_enrollments (student_id, section_course_id) VALUES (?, ?)");
            $stmt->execute([$student_id, $section_course_id]);
            
            // Update enrollment count
            $stmt = $conn->prepare("UPDATE section_courses SET current_enrollment = current_enrollment + 1 WHERE section_course_id = ?");
            $stmt->execute([$section_course_id]);
            
            // Add to student_subjects if not exists
            $stmt = $conn->prepare("
                INSERT IGNORE INTO student_subjects (student_id, course_id, status) 
                SELECT ?, sc.course_id, 'Enrolled' 
                FROM section_courses sc 
                WHERE sc.section_course_id = ?
            ");
            $stmt->execute([$student_id, $section_course_id]);
        }
        
        // Clear temporary enrollments after successful submission
        $_SESSION['temp_enrollments'] = [];
        
        // ✅ NEW: Generate and save PDF after successful enrollment
       
        
        $conn->commit();
         $pdfResult = generateAndSaveAdvisingForm($student_id, $conn);
        // ✅ MODIFIED: Include PDF generation status in response
        echo json_encode([
            'success' => true, 
            'message' => 'Enrollment submitted successfully! Reference ID: ' . $reference_id,
            'reference_id' => $reference_id,
            'pdf_generated' => $pdfResult['success'],
            'pdf_message' => $pdfResult['message']
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}
// ====================================================
// 🔹 AJAX: Add to temporary enrollment (SESSION ONLY)
// ====================================================
if (isset($_POST['action']) && $_POST['action'] === 'add_temp_enrollment') {
    $section_course_id = intval($_POST['section_course_id']);
    $course_code = $_POST['course_code'];
    $course_title = $_POST['course_title'];
    $section_name = $_POST['section_name'];
    $schedule = $_POST['schedule'];
    $units = $_POST['units']; // ADD THIS LINE
    
    try {
        // Check if already in temp enrollments
        foreach ($_SESSION['temp_enrollments'] as $enrollment) {
            if ($enrollment['section_course_id'] == $section_course_id) {
                throw new Exception("Already added to selection");
            }
        }

        // NEW: Check if same course is already in temp enrollments (different section)
        $stmt = $conn->prepare("SELECT course_id FROM section_courses WHERE section_course_id = ?");
        $stmt->execute([$section_course_id]);
        $current_course = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_course_id = $current_course['course_id'];

        foreach ($_SESSION['temp_enrollments'] as $enrollment) {
            $stmt = $conn->prepare("SELECT course_id FROM section_courses WHERE section_course_id = ?");
            $stmt->execute([$enrollment['section_course_id']]);
            $existing_course = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_course['course_id'] == $current_course_id) {
                throw new Exception("You have already selected another section for " . $course_code);
            }
        }
        
        // Check capacity
        $stmt = $conn->prepare("SELECT max_capacity, current_enrollment FROM section_courses WHERE section_course_id = ?");
        $stmt->execute([$section_course_id]);
        $section = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($section['current_enrollment'] >= $section['max_capacity']) {
            throw new Exception("Section is full");
        }
        
        // Check conflicts with database enrollments
        $stmt = $conn->prepare("
            SELECT 
                c.course_code as conflicting_course,
                s.section_name as conflicting_section
            FROM student_enrollments se
            JOIN section_courses sc ON se.section_course_id = sc.section_course_id
            JOIN section_course_schedules scs ON sc.section_course_id = scs.section_course_id
            JOIN courses c ON sc.course_id = c.course_id
            JOIN sections s ON sc.section_id = s.section_id
            WHERE se.student_id = ?
            AND EXISTS (
                SELECT 1 FROM section_course_schedules new_scs
                WHERE new_scs.section_course_id = ?
                AND new_scs.day_of_week = scs.day_of_week
                AND (
                    (new_scs.start_time BETWEEN scs.start_time AND DATE_SUB(scs.end_time, INTERVAL 1 MINUTE)) OR
                    (new_scs.end_time BETWEEN DATE_ADD(scs.start_time, INTERVAL 1 MINUTE) AND scs.end_time) OR
                    (new_scs.start_time <= scs.start_time AND new_scs.end_time >= scs.end_time)
                )
            )
        ");
        $stmt->execute([$student_id, $section_course_id]);
        if ($conflict = $stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception("Schedule conflict with " . $conflict['conflicting_course'] . " (" . $conflict['conflicting_section'] . ")");
        }
        
        // Check conflicts with other temp enrollments
        foreach ($_SESSION['temp_enrollments'] as $temp_enrollment) {
            $stmt = $conn->prepare("
                SELECT scs1.day_of_week, scs1.start_time, scs1.end_time 
                FROM section_course_schedules scs1 
                WHERE scs1.section_course_id = ?
                AND EXISTS (
                    SELECT 1 FROM section_course_schedules scs2 
                    WHERE scs2.section_course_id = ?
                    AND scs2.day_of_week = scs1.day_of_week
                    AND (
                        (scs2.start_time BETWEEN scs1.start_time AND DATE_SUB(scs1.end_time, INTERVAL 1 MINUTE)) OR
                        (scs2.end_time BETWEEN DATE_ADD(scs1.start_time, INTERVAL 1 MINUTE) AND scs1.end_time) OR
                        (scs2.start_time <= scs1.start_time AND scs2.end_time >= scs1.end_time)
                    )
                )
            ");
            $stmt->execute([$section_course_id, $temp_enrollment['section_course_id']]);
            if ($stmt->fetch()) {
                throw new Exception("Schedule conflict with: " . $temp_enrollment['course_code'] . " (" . $temp_enrollment['section_name'] . ")");
            }
        }
        
        // Add to temporary enrollment with units
        $_SESSION['temp_enrollments'][] = [
            'section_course_id' => $section_course_id,
            'course_code' => $course_code,
            'course_title' => $course_title,
            'section_name' => $section_name,
            'schedule' => $schedule,
            'units' => $units // ADD THIS LINE
        ];
        
        echo json_encode(['success' => true, 'message' => 'Added to selection']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}
// ====================================================
// 🔹 AJAX: Remove from temporary enrollment
// ====================================================
if (isset($_POST['action']) && $_POST['action'] === 'remove_temp_enrollment') {
    $section_course_id = intval($_POST['section_course_id']);
    
    try {
        $found = false;
        foreach ($_SESSION['temp_enrollments'] as $key => $enrollment) {
            if ($enrollment['section_course_id'] == $section_course_id) {
                unset($_SESSION['temp_enrollments'][$key]);
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            throw new Exception("Subject not found in selection");
        }
        
        // Reindex array
        $_SESSION['temp_enrollments'] = array_values($_SESSION['temp_enrollments']);
        
        echo json_encode(['success' => true, 'message' => 'Removed from selection']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ====================================================
// 🔹 AJAX: Get temporary enrolled subjects
// ====================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_temp_enrolled_subjects') {
    echo json_encode($_SESSION['temp_enrollments'] ?? []);
    exit();
}
// ====================================================
// 🔹 AJAX: fetch available subjects with sections (only available + current semester)
// ====================================================
if (isset($_GET['action']) && $_GET['action'] === 'fetch_available_subjects') {
    $current_semester = getCurrentSemester($conn);
    
    // Get student's passed courses for prerequisite checking
    $stmt = $conn->prepare("SELECT course_id FROM student_subjects WHERE student_id = ? AND status = 'passed'");
    $stmt->execute([$student_id]);
    $passed_courses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get course IDs that are already in temp enrollments
    $temp_course_ids = [];
    foreach ($_SESSION['temp_enrollments'] as $temp_enrollment) {
        $stmt = $conn->prepare("SELECT course_id FROM section_courses WHERE section_course_id = ?");
        $stmt->execute([$temp_enrollment['section_course_id']]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($course) {
            $temp_course_ids[] = $course['course_id'];
        }
    }
    
    // FIXED: Include units in the query
    $stmt = $conn->prepare("
        SELECT DISTINCT c.course_id, c.course_code, c.course_title, c.year_level, c.units 
        FROM courses c
        WHERE c.semester = ? 
        AND c.course_id NOT IN (
            SELECT course_id FROM student_subjects 
            WHERE student_id = ? AND status IN ('passed', 'failed', 'enrolled')
        )
        " . (empty($temp_course_ids) ? "" : "AND c.course_id NOT IN (" . implode(',', array_fill(0, count($temp_course_ids), '?')) . ")") . "
        ORDER BY c.year_level, c.course_code
    ");
    
    $params = [$current_semester, $student_id];
    if (!empty($temp_course_ids)) {
        $params = array_merge($params, $temp_course_ids);
    }
    
    $stmt->execute($params);
    $available_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check prerequisites for each course
    $result = [];
    foreach ($available_courses as $course) {
        $can_take = true;
        $missing_prereqs = [];
        
        // Check if course has prerequisites
        $stmt = $conn->prepare("
            SELECT cp.prerequisite_course_id, preq.course_code 
            FROM course_prerequisites cp
            JOIN courses preq ON cp.prerequisite_course_id = preq.course_id
            WHERE cp.course_id = ?
        ");
        $stmt->execute([$course['course_id']]);
        $prerequisites = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($prerequisites as $prereq) {
            if (!in_array($prereq['prerequisite_course_id'], $passed_courses)) {
                $can_take = false;
                $missing_prereqs[] = $prereq['course_code'];
            }
        }
        
        if ($can_take) {
            // Get available sections for this course
            $stmt = $conn->prepare("
                SELECT 
                    sc.section_course_id,
                    s.section_name,
                    s.year_level,
                    sc.max_capacity,
                    sc.current_enrollment,
                    (sc.max_capacity - sc.current_enrollment) as available_slots,
                    GROUP_CONCAT(
                        CONCAT(scs.day_of_week, ' ', TIME_FORMAT(scs.start_time, '%h:%i %p'), '-', TIME_FORMAT(scs.end_time, '%h:%i %p')) 
                        ORDER BY FIELD(scs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                        scs.start_time
                        SEPARATOR ', '
                    ) as schedule
                FROM section_courses sc
                JOIN sections s ON sc.section_id = s.section_id
                LEFT JOIN section_course_schedules scs ON sc.section_course_id = scs.section_course_id
                WHERE sc.course_id = ? AND sc.semester = ?
                GROUP BY sc.section_course_id, s.section_name, s.year_level, sc.max_capacity, sc.current_enrollment
                HAVING available_slots > 0
            ");
            $stmt->execute([$course['course_id'], $current_semester]);
            $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($sections)) {
                // Check for conflicts for each section - ENHANCED VERSION
                $sections_with_conflicts = [];
                foreach ($sections as $section) {
                    $has_conflict = false;
                    $conflicting_courses = [];
                    
                    // Check conflicts with database enrollments
                    $stmt_conflict = $conn->prepare("
                        SELECT 
                            c.course_code as conflicting_course,
                            s.section_name as conflicting_section,
                            scs.day_of_week,
                            TIME_FORMAT(scs.start_time, '%H:%i') as start_time,
                            TIME_FORMAT(scs.end_time, '%H:%i') as end_time,
                            CONCAT(scs.day_of_week, ' ', TIME_FORMAT(scs.start_time, '%h:%i %p'), '-', TIME_FORMAT(scs.end_time, '%h:%i %p')) as conflict_schedule
                        FROM student_enrollments se
                        JOIN section_courses sc ON se.section_course_id = sc.section_course_id
                        JOIN section_course_schedules scs ON sc.section_course_id = scs.section_course_id
                        JOIN courses c ON sc.course_id = c.course_id
                        JOIN sections s ON sc.section_id = s.section_id
                        WHERE se.student_id = ?
                        AND EXISTS (
                            SELECT 1 FROM section_course_schedules new_scs
                            WHERE new_scs.section_course_id = ?
                            AND new_scs.day_of_week = scs.day_of_week
                            AND (
                                (new_scs.start_time BETWEEN scs.start_time AND DATE_SUB(scs.end_time, INTERVAL 1 MINUTE)) OR
                                (new_scs.end_time BETWEEN DATE_ADD(scs.start_time, INTERVAL 1 MINUTE) AND scs.end_time) OR
                                (new_scs.start_time <= scs.start_time AND new_scs.end_time >= scs.end_time)
                            )
                        )
                    ");
                    $stmt_conflict->execute([$student_id, $section['section_course_id']]);
                    $conflicts = $stmt_conflict->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($conflicts)) {
                        $has_conflict = true;
                        $conflicting_courses = $conflicts;
                    }
                    
                    // Also check conflicts with temporary enrollments
                    if (!$has_conflict && !empty($_SESSION['temp_enrollments'])) {
                        foreach ($_SESSION['temp_enrollments'] as $temp_enrollment) {
                            $stmt_temp_conflict = $conn->prepare("
                                SELECT scs1.day_of_week, scs1.start_time, scs1.end_time 
                                FROM section_course_schedules scs1 
                                WHERE scs1.section_course_id = ?
                                AND EXISTS (
                                    SELECT 1 FROM section_course_schedules scs2 
                                    WHERE scs2.section_course_id = ?
                                    AND scs2.day_of_week = scs1.day_of_week
                                    AND (
                                        (scs2.start_time BETWEEN scs1.start_time AND DATE_SUB(scs1.end_time, INTERVAL 1 MINUTE)) OR
                                        (scs2.end_time BETWEEN DATE_ADD(scs1.start_time, INTERVAL 1 MINUTE) AND scs1.end_time) OR
                                        (scs2.start_time <= scs1.start_time AND scs2.end_time >= scs1.end_time)
                                    )
                                )
                            ");
                            $stmt_temp_conflict->execute([$section['section_course_id'], $temp_enrollment['section_course_id']]);
                            if ($stmt_temp_conflict->fetch()) {
                                $has_conflict = true;
                                $conflicting_courses[] = [
                                    'conflicting_course' => $temp_enrollment['course_code'],
                                    'conflicting_section' => $temp_enrollment['section_name'],
                                    'conflict_schedule' => $temp_enrollment['schedule'],
                                    'day_of_week' => 'Multiple days',
                                    'start_time' => 'Various',
                                    'end_time' => 'Various'
                                ];
                                break;
                            }
                        }
                    }
                    
                    // Add conflict information to section
                    $section['has_conflict'] = $has_conflict;
                    $section['conflicting_courses'] = $conflicting_courses;
                    $sections_with_conflicts[] = $section;
                }
                
                $result[] = [
                    'course_id' => $course['course_id'],
                    'course_code' => $course['course_code'],
                    'course_title' => $course['course_title'],
                    'year_level' => $course['year_level'],
                    'units' => $course['units'],
                    'sections' => $sections_with_conflicts
                ];
            }
        }
    }
    
    echo json_encode($result);
    exit();
}
// ====================================================
// 🔹 AJAX: Check for schedule conflicts
// ====================================================
if (isset($_GET['action']) && $_GET['action'] === 'check_conflicts') {
    $section_course_id = intval($_GET['section_course_id']);
    
    $stmt = $conn->prepare("
        SELECT 
            c.course_code as conflicting_course,
            s.section_name as conflicting_section,
            scs.day_of_week,
            scs.start_time,
            scs.end_time
        FROM student_enrollments se
        JOIN section_courses sc ON se.section_course_id = sc.section_course_id
        JOIN section_course_schedules scs ON sc.section_course_id = scs.section_course_id
        JOIN courses c ON sc.course_id = c.course_id
        JOIN sections s ON sc.section_id = s.section_id
        WHERE se.student_id = ?
        AND EXISTS (
            SELECT 1 FROM section_course_schedules new_scs
            WHERE new_scs.section_course_id = ?
            AND new_scs.day_of_week = scs.day_of_week
            AND (
                (new_scs.start_time BETWEEN scs.start_time AND DATE_SUB(scs.end_time, INTERVAL 1 MINUTE)) OR
                (new_scs.end_time BETWEEN DATE_ADD(scs.start_time, INTERVAL 1 MINUTE) AND scs.end_time) OR
                (new_scs.start_time <= scs.start_time AND new_scs.end_time >= scs.end_time)
            )
        )
    ");
    $stmt->execute([$student_id, $section_course_id]);
    $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['has_conflicts' => !empty($conflicts), 'conflicts' => $conflicts]);
    exit();
}

// ====================================================
// 🔹 REMOVED: AJAX: Enroll in section (NO LONGER USED)
// ====================================================

// ====================================================
// 🔹 REMOVED: AJAX: Get enrolled subjects (NO LONGER USED)
// ====================================================

// ====================================================
// 🔹 REMOVED: AJAX: Remove enrollment (NO LONGER USED)
// ====================================================

// ====================================================
// 🔹 KEEP ALL YOUR EXISTING EVALUATION CODE BELOW
// ====================================================

// 🔹 AJAX: fetch subjects for evaluation (with semester restriction)
if (isset($_GET['action']) && $_GET['action'] === 'fetch_evaluation') {
    // YOUR EXISTING EVALUATION CODE HERE - DON'T CHANGE THIS
    // [Keep all your original evaluation code exactly as it was]
    $year = isset($_GET['year']) ? intval($_GET['year']) : 1;

    $debug_info = []; // Store debug info to return

    // Get global current semester
    $current_semester = getCurrentSemester($conn);
    
    $debug_info[] = "=== EVALUATION DEBUG ===";
    $debug_info[] = "Student ID from session: " . $student_id;
    $debug_info[] = "Requested year: " . $year;
    $debug_info[] = "Current semester (GLOBAL): " . $current_semester;

    // Fetch all courses for the selected year
    $stmt = $conn->prepare("SELECT course_id, course_code, course_title, semester FROM courses WHERE year_level = ? ORDER BY semester, course_code");
    $stmt->execute([$year]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch student's subject statuses
    $stmt2 = $conn->prepare("SELECT course_id, status FROM student_subjects WHERE student_id = ?");
    $stmt2->execute([$student_id]);
    $studentSubjects = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Extensive debugging
    $debug_info[] = "Total courses found for year $year: " . count($courses);
    $debug_info[] = "Student subjects found: " . count($studentSubjects);
    
    if (empty($studentSubjects)) {
        $debug_info[] = "⚠️ NO STUDENT SUBJECTS FOUND FOR STUDENT ID: " . $student_id;
    } else {
        $debug_info[] = "📚 STUDENT SUBJECTS:";
        foreach ($studentSubjects as $subject) {
            $debug_info[] = "  Course ID: " . $subject['course_id'] . " - Status: '" . $subject['status'] . "'";
        }
    }

    $studentStatusMap = [];
    foreach ($studentSubjects as $s) {
        $studentStatusMap[$s['course_id']] = strtolower(trim($s['status']));
    }

    // Debug the status map
    $debug_info[] = "🎯 STATUS MAP:";
    foreach ($studentStatusMap as $courseId => $status) {
        $debug_info[] = "  Course $courseId -> '$status'";
    }

    // Fetch prerequisites with course codes
    $stmt3 = $conn->prepare("
        SELECT cp.course_id, cp.prerequisite_course_id, c.course_code 
        FROM course_prerequisites cp 
        JOIN courses c ON cp.prerequisite_course_id = c.course_id
    ");
    $stmt3->execute();
    $prereqs = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    $prereqMap = [];
    $prereqCodeMap = [];
    foreach ($prereqs as $p) {
        $prereqMap[$p['course_id']][] = $p['prerequisite_course_id'];
        if (!isset($prereqCodeMap[$p['course_id']])) {
            $prereqCodeMap[$p['course_id']] = [];
        }
        $prereqCodeMap[$p['course_id']][] = $p['course_code'];
    }

    // Debug prerequisites
    $debug_info[] = "🔗 PREREQUISITES:";
    foreach ($prereqMap as $courseId => $prereqs) {
        $debug_info[] = "  Course $courseId requires: " . implode(', ', $prereqs);
    }

    // Build the evaluation result
    $result = [];
    $debug_info[] = "📊 PROCESSING COURSES:";
    
    foreach ($courses as $c) {
        $status = $studentStatusMap[$c['course_id']] ?? 'not_enrolled';
        $prerequisite_text = '';
        $can_take = false; // Default to FALSE - student CANNOT take the subject

        // Debug each course
        $originalStatus = $studentStatusMap[$c['course_id']] ?? 'not_found';
        $debug_info[] = "📖 Processing: " . $c['course_code'] . " (ID: " . $c['course_id'] . ", Semester: " . $c['semester'] . ")";
        $debug_info[] = "   Raw status from DB: '" . $originalStatus . "'";
        $debug_info[] = "   Initial status: '" . $status . "'";

        // 🔥 GLOBAL SEMESTER LOGIC: Check if course is in current global semester
        if ($c['semester'] != $current_semester) {
            // Course is NOT in current semester
            if ($status === 'passed') {
                $status = 'passed';
                $debug_info[] = "   ✅ PASSED (but not current semester - keeping as passed)";
            } else {
                $status = 'not_available';
                $debug_info[] = "   🔒 NOT AVAILABLE (wrong semester - current global semester is $current_semester)";
            }
            $can_take = false;
        } else {
            // Course IS in current semester - apply normal logic
            $debug_info[] = "   ✅ Course is in current semester ($current_semester)";

            // ALWAYS show prerequisites if the subject has any (regardless of status)
            if (!empty($prereqCodeMap[$c['course_id']])) {
                $prerequisite_text = 'Prerequisite: ' . implode(', ', $prereqCodeMap[$c['course_id']]);
                $debug_info[] = "   📝 Prerequisites found: " . $prerequisite_text;
                $debug_info[] = "   🔍 DEBUG: Course ID " . $c['course_id'] . " has prerequisites, status is: " . $status;
            }

            // If status is already 'passed' or 'dropped' or 'failed', keep it as is
            if ($status === 'passed' || $status === 'dropped' || $status === 'failed') {
                $debug_info[] = "   ✅ Keeping final status: " . $status;
                $can_take = false; // Can't take subjects already passed/dropped/failed
            }
            // Check prerequisites for subjects that are not enrolled
            else if ($status === 'not_enrolled' || $status === 'not enrolled') {
                $debug_info[] = "   🔍 DEBUG: Entering not_enrolled section for " . $c['course_code'];
                // If subject has prerequisites, check if ALL are passed
                if (!empty($prereqMap[$c['course_id']])) {
                    $debug_info[] = "   🔍 Checking prerequisites for: " . $c['course_code'];
                    $all_prereqs_passed = true;
                    $missing_prereqs = [];
                    
                    foreach ($prereqMap[$c['course_id']] as $pre) {
                        $preStatus = $studentStatusMap[$pre] ?? 'not_found';
                        $debug_info[] = "     Prereq Course ID $pre -> Status: '$preStatus'";
                        // Check if prerequisite is PASSED (only 'passed' counts)
                        if ($preStatus !== 'passed') {
                            $all_prereqs_passed = false;
                            // Get the course code for the missing prerequisite
                            $stmt_pre = $conn->prepare("SELECT course_code FROM courses WHERE course_id = ?");
                            $stmt_pre->execute([$pre]);
                            $pre_course = $stmt_pre->fetch(PDO::FETCH_ASSOC);
                            $missing_prereqs[] = $pre_course['course_code'];
                            $debug_info[] = "     ❌ Missing prerequisite: Course ID $pre (Status: $preStatus)";
                        } else {
                            $debug_info[] = "     ✅ Prerequisite PASSED: Course ID $pre";
                        }
                    }
                    
                    if ($all_prereqs_passed) {
                        $can_take = true; // Can take - ALL prerequisites are PASSED
                        $status = 'available';
                        $debug_info[] = "   ✅ ALL prerequisites PASSED - CAN TAKE";
                    } else {
                        $can_take = false; // Can't take - some prerequisites not passed
                        $status = 'missing_prereq';
                        $debug_info[] = "   ⚠️ Missing prerequisites - CANNOT TAKE";
                    }
                } else {
                    // No prerequisites - student can take the subject
                    $can_take = true;
                    $status = 'available';
                    $debug_info[] = "   ✅ No prerequisites - CAN TAKE";
                }
            }
        }
        
        $result[] = [
            'course_id' => $c['course_id'],
            'course_code' => $c['course_code'],
            'course_title' => $c['course_title'],
            'semester' => $c['semester'],
            'student_status' => $status,
            'prerequisite_text' => $prerequisite_text,
            'can_take' => $can_take
        ];
        
        $debug_info[] = "---";
    }

    $debug_info[] = "=== END EVALUATION DEBUG ===";

    // Return both result and debug info
    echo json_encode([
        'result' => $result,
        'debug' => $debug_info
    ]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>E-RIS | Enrollment Form</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
/* KEEP ALL YOUR EXISTING CSS STYLES AND ADD THESE: */
.signature-section {
    border-top: 2px solid #800000;
    padding-top: 20px;
}

.signature-section .card {
    border: 1px solid #800000;
}

#signatureImage {
    max-width: 200px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
}
.subject-modal .section-option {
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
}
.subject-modal .section-option:hover {
    background-color: #f8f9fa;
    border-color: #800000;
}
.subject-modal .section-option.selected {
    background-color: #e7f1ff;
    border-color: #800000;
}
.subject-modal .conflict-warning {
    color: #dc3545;
    font-size: 0.9em;
}
.subject-modal .schedule-info {
    font-size: 0.85em;
    color: #6c757d;
}
/* Enhanced conflict styling */
.conflict-section {
    background-color: #fff3cd !important;
    border-color: #ffc107 !important;
    border-width: 2px !important;
    opacity: 0.8;
    position: relative;
}

.conflict-section:hover {
    background-color: #ffeaa7 !important;
    opacity: 1;
}

.conflict-section::before {
    content: "⛔";
    position: absolute;
    top: -8px;
    left: -8px;
    font-size: 1.2em;
    background: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #dc3545;
}

.conflict-warning {
    color: #856404;
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
    padding: 8px 12px;
    font-size: 0.85em;
}

.conflict-warning strong {
    color: #856404;
}

/* Badge styling for conflict indicator */
.badge.bg-danger {
    font-size: 0.7em;
    padding: 4px 8px;
}

/* Schedule badge styling */
.badge.bg-secondary {
    font-size: 0.75em;
    margin-bottom: 2px;
}

/* Enhanced section option styling */
.section-option {
    transition: all 0.3s ease;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    cursor: pointer;
}

.section-option:hover:not(.conflict-section) {
    background-color: #f8f9fa;
    border-color: #800000;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.section-option.selected {
    background-color: #e7f1ff;
    border-color: #800000;
}

/* Disabled button styling */
.btn-warning:disabled {
    background-color: #ffc107;
    border-color: #ffc107;
    opacity: 0.6;
    cursor: not-allowed;
}

/* Tooltip customization */
.tooltip {
    font-size: 0.8rem;
}

.tooltip .tooltip-inner {
    background-color: #800000;
    color: white;
}
/* KEEP ALL YOUR ORIGINAL CSS STYLES BELOW */
body { background-color: #f8f9fa; }
.navbar { background-color: #800000 !important; }
.text-maroon { color: #800000 !important; }
.card { border-radius: 10px; }
.year-btn.active { background-color: #800000; color: white; }
.btn-outline-maroon {
  border: 1px solid #800000;
  color: #800000;
}
.btn-outline-maroon:hover {
  background-color: #800000;
  color: white;
}
/* UPDATED COLOR LOGIC */
.status-passed td { background-color: #d1ecf1 !important; color: #0c5460 !important; }       /* Pastel Blue */
.status-dropped td { background-color: #d6d8db !important; color: #383d41 !important; }     /* Dark Gray */
.status-failed td { background-color: #f8d7da !important; color: #721c24 !important; }      /* Pastel Red */
.status-available td { background-color: transparent !important; color: #000000 !important; } /* NO BACKGROUND - Can take */
.status-missing-prereq td { background-color: #e9ecef !important; color: #6c757d !important; opacity: 0.7; } /* Grayed out - Can't take */
.status-not-available td { 
    background-color: #e9ecef !important; 
    color: #6c757d !important; 
    opacity: 0.6; 
    text-decoration: line-through;
}
.selected-subject { border: 2px solid #800000 !important; }
.table-hover tbody tr:hover { background-color: rgba(0,0,0,.075); }
.debug-info { 
    background-color: #f8f9fa; 
    border: 1px solid #dee2e6; 
    border-radius: 5px; 
    padding: 15px; 
    margin-top: 20px; 
    font-family: monospace; 
    font-size: 12px; 
    max-height: 300px; 
    overflow-y: auto; 
    white-space: pre-wrap; 
}
.semester-divider {
    border-top: 3px solid #800000;
    margin: 10px 0;
}
.semester-header {
    background-color: #f8f9fa;
    font-weight: bold;
    color: #800000;
    padding: 8px 12px;
    border-left: 4px solid #800000;
}
.prerequisite-text {
    font-size: 0.85rem;
    color: #6c757d;
    font-style: italic;
    margin-top: 2px;
}
/* Fixed Table Borders */
.evaluation-table {
    border: 2px solid #800000 !important;
}
.evaluation-table th,
.evaluation-table td {
    border: 1px solid #800000 !important;
}
.evaluation-table thead th {
    background-color: #800000 !important;
    color: white !important;
    border-bottom: 2px solid #800000 !important;
}
.selected-subjects-table {
    border: 2px solid #800000 !important;
    table-layout: fixed;
    width: 100%;
}
.selected-subjects-table th,
.selected-subjects-table td {
    border: 1px solid #800000 !important;
}
.selected-subjects-table thead th {
    background-color: #800000 !important;
    color: white !important;
    border-bottom: 2px solid #800000 !important;
}
.current-semester-badge {
    background-color: #800000;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    margin-left: 10px;
}
.submit-section {
    border-top: 2px solid #800000;
    padding-top: 20px;
    margin-top: 20px;
}
/* Column widths for Selected Subjects Table */
.selected-subjects-table th:nth-child(1),
.selected-subjects-table td:nth-child(1) { /* Subject Code */
    width: 15%; 
    white-space: nowrap;
}

.selected-subjects-table th:nth-child(2),
.selected-subjects-table td:nth-child(2) { /* Title */
    width: 25%; 
    white-space: normal;
}

.selected-subjects-table th:nth-child(3),
.selected-subjects-table td:nth-child(3) { /* Units */
    width: 8%; 
    white-space: nowrap;
    text-align: center;
}

.selected-subjects-table th:nth-child(4),
.selected-subjects-table td:nth-child(4) { /* Section */
    width: 12%; 
    white-space: nowrap;
    text-align: center;
}

.selected-subjects-table th:nth-child(5),
.selected-subjects-table td:nth-child(5) { /* Schedule */
    width: 30%; 
    white-space: normal;
}

.selected-subjects-table th:nth-child(6),
.selected-subjects-table td:nth-child(6) { /* Action */
    width: 10%; 
    white-space: nowrap;
    text-align: center;
}

/* Schedule cell styling - FIXED FOR WRAPPING */
.schedule-cell {
    white-space: normal !important;
    word-wrap: break-word;
    word-break: break-word;
    font-size: 0.8rem;
    line-height: 1.4;
}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark shadow">
  <div class="container">
    <a class="navbar-brand fw-bold" href="dashboard.php">
      <img src="assets/img/vid.jpg" width="40" height="40" class="me-2 rounded-circle"> E-RIS
    </a>
    <div class="ms-auto text-white">
      Welcome, <?= htmlspecialchars($_SESSION['student_name'] ?? 'Student'); ?>
    </div>
  </div>
</nav>

<div class="container-fluid py-5">
  <div class="row justify-content-center">
    <!-- LEFT SIDE - UPDATED -->
    <div class="col-md-5">
      <div class="card shadow-sm p-4 mb-4">
        <h5 class="text-maroon fw-bold mb-3">
          <i class="bi bi-book me-2"></i>Add Subjects
          <span class="current-semester-badge" id="currentSemesterBadge">Loading...</span>
        </h5>

        <!-- Changed from dropdown to button that opens modal -->
        <!-- Smart Recommendation Button -->
<button id="getRecommendationBtn" class="btn btn-outline-maroon w-100 mb-2">
  <i class="bi bi-stars me-2"></i> Smart Schedule Recommendation
</button>

<!-- Original browse button -->
<button id="browseSubjectsBtn" class="btn btn-primary w-100 mb-3">
  <i class="bi bi-search me-2"></i> Browse Available Subjects Manually
</button>

<!-- Recommendation Panel (hidden by default) -->
<div id="recommendationPanel" style="display:none;" class="mb-3">
  <div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:#fff5f5;border-bottom:1px solid #f0c0c0;">
      <div>
        <span class="fw-bold text-maroon" style="font-size:0.95rem;">
          <i class="bi bi-stars me-1"></i> Recommended Schedule
        </span>
        <div id="recSummary" class="small text-muted mt-1"></div>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" id="dismissRecBtn">Dismiss</button>
        <button class="btn btn-sm btn-maroon" id="acceptRecBtn">Use this schedule</button>
      </div>
    </div>
    <div class="card-body p-2" id="recommendationList"></div>
    <div class="card-footer text-muted" style="font-size:0.75rem;background:transparent;">
      <i class="bi bi-info-circle me-1"></i>You can still add or remove subjects after accepting.
    </div>
  </div>
</div>

        <!-- Selected Subjects Table -->
<h6 class="text-maroon fw-bold">Selected Subjects</h6>
<div class="table-responsive">
  <table class="table table-sm table-bordered align-middle selected-subjects-table" id="selectedSubjectsTable">
    <thead class="table-light">
      <tr>
        <th>Subject Code</th>
        <th>Title</th>
        <th class="text-center">Units</th>
        <th class="text-center">Section</th>
        <th>Schedule</th>
        <th class="text-center">Action</th>
      </tr>
    </thead>
    <tbody>
      <!-- This will be populated by JavaScript -->
      <tr>
        <td colspan="6" class="text-center text-muted py-3">
          <i class="bi bi-inbox fs-1 d-block mb-2"></i>
          No subjects selected yet<br>
          <small class="text-muted">Click "Browse Available Subjects" to add courses</small>
        </td>
      </tr>
    </tbody>
  </table>
</div>
<!-- Required Forms Section -->
<div class="signature-section mb-4">
    <h6 class="text-maroon fw-bold mb-3">Required Documents</h6>
    
    <!-- Signature Upload (All Students) -->
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="fw-bold">Digital Signature <span class="text-danger">*</span></h6>
            <div id="signaturePreview" class="text-center mb-3" style="display: none;">
                <p class="mb-2"><strong>Current Signature:</strong></p>
                <img id="signatureImage" src="" alt="Signature" class="img-fluid border rounded" style="max-height: 100px;">
            </div>
            <div id="noSignature" class="text-center mb-3">
                <p class="text-muted">No signature uploaded yet</p>
            </div>
            <form id="signatureForm" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="signatureFile" class="form-label">Upload Signature Image</label>
                    <input type="file" class="form-control" id="signatureFile" accept="image/jpeg,image/jpg,image/png,image/gif" required>
                    <div class="form-text">Supported formats: JPG, PNG, GIF (Max: 2MB)<br>  By signing this document:<br>
                    <ol>
 <li>I fully understand and agree that the information above are true and correct; and that any false statement on this document
 (as well as on the supporting documents) constitutes a perjury and could result in denial of the registration and disciplinary
 proceedings with sanction as per the Don Honorio Ventura State University (the "University") policies;</li>
 <li>I am fully aware and understand that registration/enrollment in any course for the abovementioned academic period is
 allowed only upon passing the pre-requisite(s) of the said course (if any); a course registered/enrolled in violation of this
 rule will not be given any credit regardless of the grade obtained;</li>
 <li>I authorize the University to collect and process any information declared herein with utmost confidentiality; and</li>
 <li>I allow the University to disclose the collected information to its affiliates and lawful third parties for legitimate purposes
 only.</li>
</ol>
</div>
                </div>
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-upload me-2"></i> Upload Signature
                </button>
            </form>
            <div id="signatureResult" class="mt-2"></div>
        </div>
    </div>

    <?php
    // Get student status from database
    $statusStmt = $conn->prepare("SELECT status FROM students WHERE id = ?");
    $statusStmt->execute([$student_id]);
    $studentStatus = $statusStmt->fetch(PDO::FETCH_ASSOC)['status'];
    ?>

    <!-- Shiftee Form Upload (Shiftee Students Only) -->
    <?php if ($studentStatus === 'Shiftee'): ?>
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="fw-bold">Shiftee Form <span class="text-danger">*</span></h6>
            <div id="shifteePreview" class="text-center mb-3" style="display: none;">
                <p class="mb-2"><strong>Current Shiftee Form:</strong></p>
                <img id="shifteeImage" src="" alt="Shiftee Form" class="img-fluid border rounded" style="max-height: 100px;">
            </div>
            <div id="noShiftee" class="text-center mb-3">
                <p class="text-muted">No shiftee form uploaded yet</p>
            </div>
            <form id="shifteeForm" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="shifteeFile" class="form-label">Upload Shiftee Form</label>
                    <input type="file" class="form-control" id="shifteeFile" accept=".pdf,image/jpeg,image/jpg,image/png" required>
                    <div class="form-text">Supported formats: PDF, JPG, PNG (Max: 2MB)</div>
                </div>
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-upload me-2"></i> Upload Shiftee Form
                </button>
            </form>
            <div id="shifteeResult" class="mt-2"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Letter of Intent Upload (Returnee Students Only) -->
    <?php if ($studentStatus === 'Returnee'): ?>
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="fw-bold">Letter of Intent <span class="text-danger">*</span></h6>
            <div id="loiPreview" class="text-center mb-3" style="display: none;">
                <p class="mb-2"><strong>Current Letter of Intent:</strong></p>
                <img id="loiImage" src="" alt="Letter of Intent" class="img-fluid border rounded" style="max-height: 100px;">
            </div>
            <div id="noLoi" class="text-center mb-3">
                <p class="text-muted">No letter of intent uploaded yet</p>
            </div>
            <form id="loiForm" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="loiFile" class="form-label">Upload Letter of Intent</label>
                    <input type="file" class="form-control" id="loiFile" accept=".pdf,image/jpeg,image/jpg,image/png" required>
                    <div class="form-text">Supported formats: PDF, JPG, PNG (Max: 2MB)</div>
                </div>
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-upload me-2"></i> Upload Letter of Intent
                </button>
            </form>
            <div id="loiResult" class="mt-2"></div>
        </div>
    </div>
    <?php endif; ?>
    
</div>
        <!-- Submit Enrollment Section -->
        <div class="submit-section">
          <h6 class="text-maroon fw-bold mb-3">Submit Enrollment</h6>
          <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Review your selected subjects above. Click submit to finalize your enrollment.
          </div>
          <button id="submitEnrollmentBtn" class="btn btn-success w-100">
            <i class="bi bi-send-check me-2"></i> Submit Enrollment Form
          </button>
          <button id="generatePDFBtn" class="btn btn-outline-primary w-100 mt-2" style="display: none;">
            <i class="bi bi-file-pdf me-2"></i> Generate PDF Form
          </button>
          <div id="submitResult" class="mt-2"></div>
        </div>
      </div>
    </div>

    <!-- RIGHT SIDE - KEEP EXACTLY AS IS -->
    <div class="col-md-7">
      <div class="card shadow-sm p-4">
        <h5 class="text-maroon fw-bold mb-3">
          <i class="bi bi-bar-chart-line me-2"></i>Subject Evaluation
          <span class="current-semester-badge" id="currentSemesterBadge2">Loading...</span>
        </h5>

        <!-- Year Buttons -->
        <div class="d-flex justify-content-around mb-3">
          <button class="btn btn-outline-maroon year-btn active" data-year="1">1st Year</button>
          <button class="btn btn-outline-maroon year-btn" data-year="2">2nd Year</button>
          <button class="btn btn-outline-maroon year-btn" data-year="3">3rd Year</button>
          <button class="btn btn-outline-maroon year-btn" data-year="4">4th Year</button>
        </div>

        <div id="evaluationResults">
          <p class="text-muted">Subjects for the selected year will appear here with status.</p>
        </div>

        <!-- Debug Information -->
        <div class="mt-4">
          <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse" data-bs-target="#debugCollapse">
            <i class="bi bi-bug"></i> Show Debug Information
          </button>
          <div class="collapse mt-2" id="debugCollapse">
            <div class="debug-info" id="debugInfo">
              Debug information will appear here when you load a year...
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Subject Modal -->
<div class="modal fade" id="subjectModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-maroon">
          <i class="bi bi-book me-2"></i>Available Subjects
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body subject-modal" id="subjectModalBody">
        <div class="text-center">
          <div class="spinner-border text-maroon" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="mt-2">Loading available subjects...</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // NEW: Modal functionality
  const browseSubjectsBtn = document.getElementById('browseSubjectsBtn');
  const selectedTable = document.querySelector('#selectedSubjectsTable tbody');
  const subjectModal = new bootstrap.Modal(document.getElementById('subjectModal'));
  const subjectModalBody = document.getElementById('subjectModalBody');
  const submitEnrollmentBtn = document.getElementById('submitEnrollmentBtn');
  const submitResult = document.getElementById('submitResult');
  const generatePDFBtn = document.getElementById('generatePDFBtn');

  // KEEP YOUR EXISTING VARIABLES
  const evaluationResults = document.getElementById('evaluationResults');
  const debugInfo = document.getElementById('debugInfo');
  const yearButtons = document.querySelectorAll('.year-btn');
  const currentSemesterBadge = document.getElementById('currentSemesterBadge');
  const currentSemesterBadge2 = document.getElementById('currentSemesterBadge2');
  // ===== SMART RECOMMENDATION =====
const getRecommendationBtn = document.getElementById('getRecommendationBtn');
const recommendationPanel  = document.getElementById('recommendationPanel');
const recommendationList   = document.getElementById('recommendationList');
const recSummary           = document.getElementById('recSummary');
const dismissRecBtn        = document.getElementById('dismissRecBtn');
const acceptRecBtn         = document.getElementById('acceptRecBtn');

let currentRecommendation = [];

getRecommendationBtn.addEventListener('click', () => {
    getRecommendationBtn.disabled = true;
    getRecommendationBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Finding best schedule...';
    recommendationPanel.style.display = 'none';

    fetch('submit_form.php?action=get_recommendation')
        .then(r => r.json())
        .then(data => {
            getRecommendationBtn.disabled = false;
            getRecommendationBtn.innerHTML = '<i class="bi bi-stars me-2"></i> Smart Schedule Recommendation';

            if (!data.success || data.recommendation.length === 0) {
                alert('No conflict-free schedule could be built from the available subjects. Try browsing manually.');
                return;
            }

            currentRecommendation = data.recommendation;
            const totalUnits = data.recommendation.reduce((s, c) => s + parseFloat(c.units), 0);
            recSummary.textContent = `${data.recommendation.length} subject${data.recommendation.length !== 1 ? 's' : ''} · ${totalUnits} units · No conflicts`;

            recommendationList.innerHTML = data.recommendation.map(c => `
                <div class="d-flex justify-content-between align-items-start p-2 border-bottom">
                    <div>
                        <div class="fw-bold" style="font-size:0.85rem;">${c.course_code} — ${c.course_title}</div>
                        <div class="text-muted" style="font-size:0.78rem;">
                            Section ${c.section_name} · ${c.schedule} · ${c.units} units
                            <span class="badge ms-1" style="background:#eaf3de;color:#3b6d11;font-size:0.7rem;">${c.available_slots} slot${c.available_slots !== 1 ? 's' : ''} left</span>
                        </div>
                    </div>
                    <span class="badge" style="background:#eaf3de;color:#3b6d11;font-size:0.7rem;white-space:nowrap;">No conflict</span>
                </div>
            `).join('');

            recommendationPanel.style.display = 'block';
        })
        .catch(() => {
            getRecommendationBtn.disabled = false;
            getRecommendationBtn.innerHTML = '<i class="bi bi-stars me-2"></i> Smart Schedule Recommendation';
            alert('Error fetching recommendation. Please try again.');
        });
});

dismissRecBtn.addEventListener('click', () => {
    recommendationPanel.style.display = 'none';
    currentRecommendation = [];
});

acceptRecBtn.addEventListener('click', async () => {
    if (!currentRecommendation.length) return;
    acceptRecBtn.disabled = true;
    acceptRecBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Adding...';

    let errors = [];
    for (const course of currentRecommendation) {
        const formData = new FormData();
        formData.append('action',           'add_temp_enrollment');
        formData.append('section_course_id', course.section_course_id);
        formData.append('course_code',       course.course_code);
        formData.append('course_title',      course.course_title);
        formData.append('section_name',      course.section_name);
        formData.append('schedule',          course.schedule);
        formData.append('units',             course.units);

        const res  = await fetch('submit_form.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (!json.success) errors.push(`${course.course_code}: ${json.message}`);
    }

    acceptRecBtn.disabled = false;
    acceptRecBtn.innerHTML = 'Use this schedule';
    recommendationPanel.style.display = 'none';
    currentRecommendation = [];

    loadTempEnrolledSubjects();

    if (errors.length) {
        alert('Some subjects could not be added:\n' + errors.join('\n'));
    }
});

  let currentYear = 1;
  let currentGlobalSemester = 1;
  let isAddingSubject = false; // Flag to prevent double clicks

  // FIX: Load current semester on page load
  function loadCurrentSemester() {
    fetch('submit_form.php?action=get_current_semester')
      .then(res => res.json())
      .then(data => {
        currentGlobalSemester = data.current_semester;
        updateSemesterBadge();
      })
      .catch(error => {
        console.error('Error loading semester:', error);
        currentGlobalSemester = 1; // Fallback
        updateSemesterBadge();
      });
  }

  function updateSemesterBadge() {
    const semesterText = `Semester ${currentGlobalSemester}`;
    currentSemesterBadge.textContent = semesterText;
    currentSemesterBadge2.textContent = semesterText;
  }

  // NEW: Load temporary enrolled subjects on page load
  loadTempEnrolledSubjects();

  // NEW: Browse subjects button click
  browseSubjectsBtn.addEventListener('click', () => {
    loadAvailableSubjects();
    subjectModal.show();
  });

  // NEW: Submit enrollment button click
  submitEnrollmentBtn.addEventListener('click', () => {
    submitEnrollment();
  });
  // Add this after the submitEnrollmentBtn event listener
generatePDFBtn.addEventListener('click', generatePDF);
  
  // NEW: Generate PDF function
  // NEW: Generate PDF function
// NEW: Generate PDF function
function generatePDF() {
    // Show loading state
    generatePDFBtn.disabled = true;
    generatePDFBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i> Generating PDF...';
    
    // Simply open the PDF generation page
    const pdfWindow = window.open('generate_pdf.php', '_blank');
    
    // Reset button after a short delay
    setTimeout(() => {
        generatePDFBtn.disabled = false;
        generatePDFBtn.innerHTML = '<i class="bi bi-file-pdf me-2"></i> Generate PDF Form';
        
        // Check if the PDF window was blocked
        if (!pdfWindow || pdfWindow.closed || typeof pdfWindow.closed === 'undefined') {
            alert('PDF generation was blocked by popup blocker. Please allow popups for this site and try again.');
        }
    }, 2000);
}

  // NEW: Submit enrollment function
  function submitEnrollment() {
    if (!confirm('Are you sure you want to submit your enrollment? This will save all selected subjects to the database and cannot be undone.')) {
      return;
    }

    submitEnrollmentBtn.disabled = true;
    submitEnrollmentBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i> Submitting...';
    submitResult.innerHTML = '';

    const formData = new FormData();
    formData.append('action', 'submit_enrollment');

    fetch('submit_form.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(result => {
      if (result.success) {
        submitResult.innerHTML = `
          <div class="alert alert-success">
            <i class="bi bi-check-circle-fill me-2"></i>
            ${result.message}
          </div>
        `;
        submitEnrollmentBtn.style.display = 'none';
        generatePDFBtn.style.display = 'block'; // Show PDF button after successful submission
        // Reload to show empty table
        loadTempEnrolledSubjects();
      } else {
        submitResult.innerHTML = `
          <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            ${result.message}
          </div>
        `;
        submitEnrollmentBtn.disabled = false;
        submitEnrollmentBtn.innerHTML = '<i class="bi bi-send-check me-2"></i> Submit Enrollment Form';
      }
    })
    .catch(error => {
      submitResult.innerHTML = `
        <div class="alert alert-danger">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          Error submitting enrollment: ${error.message}
        </div>
      `;
      submitEnrollmentBtn.disabled = false;
      submitEnrollmentBtn.innerHTML = '<i class="bi bi-send-check me-2"></i> Submit Enrollment Form';
    });
  }

  // NEW: Load available subjects for modal
  // NEW: Load available subjects for modal - DEBUG VERSION
function loadAvailableSubjects() {
    subjectModalBody.innerHTML = `
      <div class="text-center">
        <div class="spinner-border text-maroon" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Loading available subjects...</p>
      </div>
    `;

    fetch('submit_form.php?action=fetch_available_subjects')
      .then(res => res.json())
      .then(subjects => {
        console.log('🔍 DEBUG - Full subjects data:', subjects);
        
        if (subjects.length === 0) {
          subjectModalBody.innerHTML = '<p class="text-muted text-center">No available subjects found.</p>';
          return;
        }

        let html = '';
        subjects.forEach((subject, index) => {
          console.log(`🔍 DEBUG - Subject ${index}:`, subject.course_code);
          console.log(`🔍 DEBUG - Sections for ${subject.course_code}:`, subject.sections);
          
          // Check if any sections have conflicts
          const hasConflicts = subject.sections.some(section => section.has_conflict);
          console.log(`🔍 DEBUG - ${subject.course_code} has conflicts:`, hasConflicts);
          
          html += `
            <div class="card mb-3">
              <div class="card-header bg-light">
                <h6 class="mb-0">${subject.course_code} - ${subject.course_title}</h6>
                <small class="text-muted">Year ${subject.year_level} | Units: ${subject.units}</small>
                ${hasConflicts ? '<span class="badge bg-warning float-end">Has Conflicts</span>' : ''}
              </div>
              <div class="card-body">
                <h6>Available Sections:</h6>
                ${renderSections(subject.sections, subject.course_code, subject.course_title, subject.units)}
              </div>
            </div>
          `;
        });

        subjectModalBody.innerHTML = html;
        attachSectionHandlers();
      })
      .catch(error => {
        subjectModalBody.innerHTML = '<p class="text-danger text-center">Error loading subjects.</p>';
        console.error('Error:', error);
      });
}
// DEBUG: Test conflict detection
function testConflictDetection() {
    console.log('🧪 Testing conflict detection...');
    
    // Test with a specific section that should have conflicts
    fetch('submit_form.php?action=check_conflicts&section_course_id=1') // Change ID as needed
        .then(res => res.json())
        .then(data => {
            console.log('🧪 Conflict test result:', data);
        })
        .catch(error => {
            console.error('🧪 Conflict test error:', error);
        });
}

// Call this function to test (you can remove it later)
// testConflictDetection();
  // NEW: Improved function to render sections with better conflict visualization
// NEW: Enhanced function to render sections with PROPER conflict visualization
function renderSections(sections, courseCode, courseTitle, units) {
    if (sections.length === 0) {
        return '<p class="text-muted">No available sections</p>';
    }

    let html = '';
    
    sections.forEach(section => {
        const hasConflict = section.has_conflict;
        const conflictClass = hasConflict ? 'conflict-section' : '';
        const availableSlots = section.available_slots;
        
        // Enhanced conflict warning with detailed information
        let conflictWarning = '';
        if (hasConflict) {
            let conflictDetails = '';
            if (section.conflicting_courses && section.conflicting_courses.length > 0) {
                conflictDetails = section.conflicting_courses.map(conflict => 
                    `<div class="small">
                         <strong>${conflict.conflicting_course}</strong> (${conflict.conflicting_section})<br>
                         <span class="text-muted">${conflict.conflict_schedule || conflict.day_of_week + ' ' + conflict.start_time + '-' + conflict.end_time}</span>
                     </div>`
                ).join('<hr class="my-1">');
            }
            
            conflictWarning = `
                <div class="conflict-warning mt-2 p-2 animate__animated animate__headShake">
                    <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                    <strong class="text-danger">SCHEDULE CONFLICT DETECTED!</strong>
                    <div class="mt-1 small">
                        This section conflicts with your current schedule:
                        ${conflictDetails}
                    </div>
                    <div class="mt-1 small text-danger">
                        <i class="bi bi-x-circle me-1"></i>You cannot enroll in this section due to schedule overlap
                    </div>
                </div>
            `;
        }
        
        // Slot availability indicator
        const slotInfo = availableSlots <= 3 ? 
            `<span class="text-warning"><i class="bi bi-exclamation-circle me-1"></i>Only ${availableSlots} slot(s) left!</span>` :
            `<span class="text-success"><i class="bi bi-check-circle me-1"></i>${availableSlots} slots available</span>`;

        const buttonClass = hasConflict ? 'btn-danger' : 'btn-outline-primary';
        const buttonText = hasConflict ? 'Conflict - Cannot Add' : 'Add to Selection';
        const buttonDisabled = hasConflict ? 'disabled' : '';

        html += `
            <div class="section-option mb-3 p-3 border rounded position-relative ${conflictClass}" 
                 data-section-course-id="${section.section_course_id}"
                 data-units="${units}"
                 data-has-conflict="${hasConflict}">
                
                <!-- Conflict Badge - More prominent -->
                ${hasConflict ? `
                    <span class="position-absolute top-0 start-0 translate-middle badge bg-danger" style="font-size: 0.7em; z-index: 10;">
                        <i class="bi bi-clock me-1"></i> CONFLICT
                    </span>
                ` : ''}
                
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h6 class="mb-1 fw-bold ${hasConflict ? 'text-danger' : ''}">
                            ${section.section_name}
                            ${hasConflict ? '<i class="bi bi-exclamation-triangle-fill text-danger ms-1"></i>' : ''}
                        </h6>
                        <p class="schedule-info mb-1 ${hasConflict ? 'text-danger' : ''}">
                            <i class="bi bi-calendar-week me-1"></i>
                            <strong>Schedule:</strong> ${formatScheduleForDisplay(section.schedule)}
                        </p>
                        <p class="mb-1 text-muted">
                            <i class="bi bi-people me-1"></i>
                            <strong>Capacity:</strong> ${section.current_enrollment}/${section.max_capacity}
                        </p>
                        <p class="mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            ${slotInfo}
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-sm ${buttonClass} add-temp-btn" ${buttonDisabled}
                                data-bs-toggle="tooltip" 
                                data-bs-title="${hasConflict ? 'This section has a schedule conflict' : 'Add this section to your selection'}"
                                style="${hasConflict ? 'cursor: not-allowed; opacity: 0.6;' : ''}">
                            <i class="bi ${hasConflict ? 'bi-x-circle' : 'bi-plus-circle'} me-1"></i>
                            ${buttonText}
                        </button>
                    </div>
                </div>
                ${conflictWarning}
                
                <!-- Quick schedule overview -->
                <div class="mt-2 p-2 bg-light rounded small">
                    <strong>Schedule Breakdown:</strong><br>
                    ${renderScheduleBreakdown(section.schedule)}
                </div>
            </div>
        `;
    });
    
    return html;
}
// NEW: Helper function to format schedule for better display
function formatScheduleForDisplay(schedule) {
    if (!schedule) return 'No schedule set';
    
    // Replace commas with line breaks and format times
    return schedule.split(', ')
        .map(session => {
            // Format: "Monday 08:00 AM-09:30 AM" -> "Mon 8:00-9:30 AM"
            return session.replace(/(\w+) (\d+:\d+ [AP]M)-(\d+:\d+ [AP]M)/, (match, day, start, end) => {
                const shortDay = day.substring(0, 3);
                return `${shortDay} ${start} - ${end}`;
            });
        })
        .join('<br>');
}

// NEW: Helper function to render detailed schedule breakdown
function renderScheduleBreakdown(schedule) {
    if (!schedule) return 'No schedule information';
    
    const sessions = schedule.split(', ');
    return sessions.map(session => {
        return `<span class="badge bg-secondary me-1 mb-1">${session}</span>`;
    }).join('');
}

// NEW: Function to check conflicts in real-time (additional safety check)
function checkRealTimeConflicts(sectionCourseId) {
    return fetch(`submit_form.php?action=check_conflicts&section_course_id=${sectionCourseId}`)
        .then(res => res.json())
        .then(data => {
            return data.has_conflicts;
        })
        .catch(error => {
            console.error('Error checking conflicts:', error);
            return false;
        });
}

  function attachSectionHandlers() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    document.querySelectorAll('.add-temp-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            // Check if button is disabled (due to conflict)
            if (this.disabled) {
                // Show more detailed conflict information
                const sectionOption = this.closest('.section-option');
                const conflictWarning = sectionOption.querySelector('.conflict-warning');
                
                if (conflictWarning) {
                    // Create a more prominent alert
                    const conflictText = conflictWarning.querySelector('strong').textContent;
                    alert(`❌ SCHEDULE CONFLICT\n\nThis section cannot be added because it conflicts with your current schedule.\n\nPlease choose a different section or time.`);
                } else {
                    alert('❌ This section cannot be added due to a schedule conflict.');
                }
                return;
            }
            
            if (isAddingSubject) {
                return;
            }
            
            isAddingSubject = true;
            const sectionOption = this.closest('.section-option');
            const sectionCourseId = sectionOption.dataset.sectionCourseId;
            const sectionName = sectionOption.querySelector('h6').textContent;
            const schedule = sectionOption.querySelector('.schedule-info').textContent.replace('Schedule: ', '');
            const hasConflict = sectionOption.dataset.hasConflict === 'true';
            
            // Additional real-time conflict check (safety net)
            const realTimeConflict = await checkRealTimeConflicts(sectionCourseId);
            if (realTimeConflict) {
                alert('❌ A schedule conflict was detected. This section conflicts with your current schedule and cannot be added.');
                isAddingSubject = false;
                return;
            }
            
            // Get course info from the card header
            const card = this.closest('.card');
            const cardHeaderText = card.querySelector('.card-header h6').textContent;
            const [courseCode, ...titleParts] = cardHeaderText.split(' - ');
            const courseTitle = titleParts.join(' - ');
            
            // Get units
            let units = '0';
            if (sectionOption.dataset.units) {
                units = sectionOption.dataset.units;
            }
            
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Adding...';
            
            addToTempEnrollment(sectionCourseId, courseCode, courseTitle, sectionName, schedule, units, sectionOption, this);
        });
    });
    
    // Add hover effects for better UX
    document.querySelectorAll('.section-option').forEach(option => {
        option.addEventListener('mouseenter', function() {
            if (!this.classList.contains('conflict-section')) {
                this.style.backgroundColor = '#f8f9fa';
                this.style.borderColor = '#800000';
            }
        });
        
        option.addEventListener('mouseleave', function() {
            if (!this.classList.contains('conflict-section')) {
                this.style.backgroundColor = '';
                this.style.borderColor = '#dee2e6';
            }
        });
    });
}
  function addToTempEnrollment(sectionCourseId, courseCode, courseTitle, sectionName, schedule, units, sectionElement, buttonElement) {
    const formData = new FormData();
    formData.append('action', 'add_temp_enrollment');
    formData.append('section_course_id', sectionCourseId);
    formData.append('course_code', courseCode);
    formData.append('course_title', courseTitle);
    formData.append('section_name', sectionName);
    formData.append('schedule', schedule);
    formData.append('units', units);

    fetch('submit_form.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(result => {
      if (result.success) {
        sectionElement.innerHTML = `
          <div class="text-center text-success">
            <i class="bi bi-check-circle-fill me-2"></i>
            Added to selection!
          </div>
        `;
        
        loadTempEnrolledSubjects();
        
        setTimeout(() => {
          subjectModal.hide();
        }, 1000);
      } else {
        alert('Failed to add: ' + result.message);
        if (buttonElement) {
          buttonElement.disabled = false;
          buttonElement.innerHTML = 'Add to Selection';
        }
      }
      isAddingSubject = false;
    })
    .catch(error => {
      alert('Error: ' + error.message);
      if (buttonElement) {
        buttonElement.disabled = false;
        buttonElement.innerHTML = 'Add to Selection';
      }
      isAddingSubject = false;
    });
}

function loadTempEnrolledSubjects() {
    fetch('submit_form.php?action=get_temp_enrolled_subjects')
      .then(res => res.json())
      .then(enrolled => {
        selectedTable.innerHTML = '';
        
        if (enrolled.length === 0) {
          selectedTable.innerHTML = `
            <tr>
              <td colspan="6" class="text-center text-muted">No subjects selected yet</td>
            </tr>
          `;
          return;
        }

        enrolled.forEach(course => {
          const row = document.createElement('tr');
          
          // FIXED: Better schedule formatting without leading blank line
          let formattedSchedule = course.schedule;
          
          // First, replace common separators with line breaks
          if (formattedSchedule.includes(', ')) {
            formattedSchedule = formattedSchedule.split(', ').join('<br>');
          }
          if (formattedSchedule.includes(' & ')) {
            formattedSchedule = formattedSchedule.split(' & ').join('<br>');
          }
          
          // FIX: Handle the case where days run together without spaces
          // This will catch patterns like "Tue...Thu..." and add line breaks AFTER the first day
          formattedSchedule = formattedSchedule.replace(/(?<=PM|AM)(Mon|Tue|Wed|Thu|Fri|Sat|Sun)/gi, '<br>$1');
          
          row.innerHTML = `
            <td>${course.course_code}</td>
            <td>${course.course_title}</td>
            <td class="text-center">${course.units}</td>
            <td class="text-center">${course.section_name}</td>
            <td class="schedule-cell">${formattedSchedule}</td>
            <td class="text-center">
              <button class="btn btn-sm btn-danger remove-temp-enrollment" data-section-course-id="${course.section_course_id}">
                <i class="bi bi-trash"></i>
              </button>
            </td>
          `;
          selectedTable.appendChild(row);
        });

        // Attach remove handlers
        document.querySelectorAll('.remove-temp-enrollment').forEach(btn => {
          btn.addEventListener('click', function() {
            const sectionCourseId = this.dataset.sectionCourseId;
            removeTempEnrollment(sectionCourseId);
          });
        });
      });
}
// NEW: Function to format schedule specifically for table display
function formatScheduleForTable(schedule) {
    if (!schedule) return '<span class="text-muted">No schedule</span>';
    
    let formatted = schedule;
    
    // Replace common separators with line breaks
    if (formatted.includes(', ')) {
        formatted = formatted.split(', ').join('<br>');
    }
    if (formatted.includes(' & ')) {
        formatted = formatted.split(' & ').join('<br>');
    }
    if (formatted.includes('; ')) {
        formatted = formatted.split('; ').join('<br>');
    }
    
    // Format individual schedule items for better readability
    formatted = formatted.replace(/(\w+) (\d+:\d+ [AP]M)-(\d+:\d+ [AP]M)/g, 
        '<span class="schedule-line">$1 $2-$3</span>');
    
    return formatted;
}
  // Signature functionality
const signatureForm = document.getElementById('signatureForm');
const signatureFile = document.getElementById('signatureFile');
const signaturePreview = document.getElementById('signaturePreview');
const signatureImage = document.getElementById('signatureImage');
const noSignature = document.getElementById('noSignature');
const signatureResult = document.getElementById('signatureResult');
// Shiftee Form functionality
const shifteeForm = document.getElementById('shifteeForm');
const shifteeFile = document.getElementById('shifteeFile');
const shifteePreview = document.getElementById('shifteePreview');
const shifteeImage = document.getElementById('shifteeImage');
const noShiftee = document.getElementById('noShiftee');
const shifteeResult = document.getElementById('shifteeResult');

// Letter of Intent functionality
const loiForm = document.getElementById('loiForm');
const loiFile = document.getElementById('loiFile');
const loiPreview = document.getElementById('loiPreview');
const loiImage = document.getElementById('loiImage');
const noLoi = document.getElementById('noLoi');
const loiResult = document.getElementById('loiResult');

// Load current forms on page load
loadCurrentShifteeForm();
loadCurrentLoiForm();

function loadCurrentShifteeForm() {
    fetch('submit_form.php?action=get_shiftee_form')
        .then(res => res.json())
        .then(data => {
            if (data.shiftee_path) {
                shifteeImage.src = data.shiftee_path + '?t=' + new Date().getTime();
                shifteePreview.style.display = 'block';
                noShiftee.style.display = 'none';
            } else {
                shifteePreview.style.display = 'none';
                noShiftee.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error loading shiftee form:', error);
        });
}

function loadCurrentLoiForm() {
    fetch('submit_form.php?action=get_loi_form')
        .then(res => res.json())
        .then(data => {
            if (data.loi_path) {
                loiImage.src = data.loi_path + '?t=' + new Date().getTime();
                loiPreview.style.display = 'block';
                noLoi.style.display = 'none';
            } else {
                loiPreview.style.display = 'none';
                noLoi.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error loading letter of intent:', error);
        });
}

// Handle shiftee form submission
if (shifteeForm) {
    shifteeForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const file = shifteeFile.files[0];
        if (!file) {
            alert('Please select a shiftee form file');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'upload_shiftee_form');
        formData.append('shiftee_form', file);
        
        shifteeResult.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Uploading...</div>';
        
        fetch('submit_form.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                shifteeResult.innerHTML = '<div class="alert alert-success">' + result.message + '</div>';
                shifteeFile.value = '';
                loadCurrentShifteeForm();
            } else {
                shifteeResult.innerHTML = '<div class="alert alert-danger">' + result.message + '</div>';
            }
        })
        .catch(error => {
            shifteeResult.innerHTML = '<div class="alert alert-danger">Error uploading shiftee form: ' + error.message + '</div>';
        });
    });
}

// Handle letter of intent submission
if (loiForm) {
    loiForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const file = loiFile.files[0];
        if (!file) {
            alert('Please select a letter of intent file');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'upload_loi_form');
        formData.append('loi_form', file);
        
        loiResult.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Uploading...</div>';
        
        fetch('submit_form.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                loiResult.innerHTML = '<div class="alert alert-success">' + result.message + '</div>';
                loiFile.value = '';
                loadCurrentLoiForm();
            } else {
                loiResult.innerHTML = '<div class="alert alert-danger">' + result.message + '</div>';
            }
        })
        .catch(error => {
            loiResult.innerHTML = '<div class="alert alert-danger">Error uploading letter of intent: ' + error.message + '</div>';
        });
    });
}
// Load current signature on page load
loadCurrentSignature();

function loadCurrentSignature() {
    fetch('submit_form.php?action=get_signature')
        .then(res => res.json())
        .then(data => {
            if (data.signature_path) {
                signatureImage.src = data.signature_path + '?t=' + new Date().getTime();
                signaturePreview.style.display = 'block';
                noSignature.style.display = 'none';
            } else {
                signaturePreview.style.display = 'none';
                noSignature.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error loading signature:', error);
        });
}
<?php
function generateAndSaveAdvisingForm($student_id) {
    // Include required files and start session if not already started
    include('includes/db_connect.php');
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    try {
        // --- FUNCTIONS ---
        function getAcademicYear($conn) {
            $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'academic_year'");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['setting_value'] : '2025-2026';
        }

        // --- FETCH CURRENT SEMESTER & ACADEMIC YEAR ---
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'current_semester'");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_semester = $row ? intval($row['setting_value']) : 1;

        $academic_year = getAcademicYear($conn);

        // --- GET SUBMISSION DATE ---
        $stmt = $conn->prepare("
            SELECT created_at 
            FROM submissions 
            WHERE student_id = ? AND type = 'enrollment' 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$student_id]);
        $submission = $stmt->fetch(PDO::FETCH_ASSOC);

        // Use submission date if available, otherwise use current date
        if ($submission && $submission['created_at']) {
            $submission_date = date('m/d/Y', strtotime($submission['created_at']));
        } else {
            $submission_date = date('m/d/Y');
        }

        // Convert semester number to string
        $semester_str = $current_semester . '<sup>' . ($current_semester == 1 ? 'ST' : ($current_semester == 2 ? 'ND' : ($current_semester == 3 ? 'RD' : 'TH'))) . '</sup>';

        // --- FETCH STUDENT DATA ---
        $stmt = $conn->prepare("
            SELECT 
                s.id,
                s.student_id,
                s.first_name,
                s.middle_name,
                s.last_name,
                s.email,
                s.mobile_number,
                s.secondary_mobile_number,
                s.year_level,
                s.major
            FROM students s
            WHERE s.id = ?
        ");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            throw new Exception("Student data not found.");
        }

        // --- FETCH ENROLLED SUBJECTS ---
        $stmt = $conn->prepare("
            SELECT 
                c.course_code,
                c.course_title,
                c.units AS credit_units,
                sec.section_name,
                GROUP_CONCAT(
                    CONCAT(scs.day_of_week, ' (', TIME_FORMAT(scs.start_time, '%h:%i %p'), ' - ', TIME_FORMAT(scs.end_time, '%h:%i %p'), ')')
                    ORDER BY FIELD(scs.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
                    scs.start_time
                    SEPARATOR ' & '
                ) AS schedule
            FROM student_enrollments se
            JOIN section_courses sc ON se.section_course_id = sc.section_course_id
            JOIN courses c ON sc.course_id = c.course_id
            JOIN sections sec ON sc.section_id = sec.section_id
            JOIN section_course_schedules scs ON sc.section_course_id = scs.section_course_id
            WHERE se.student_id = ?
            GROUP BY c.course_code, c.course_title, c.units, sec.section_name
            ORDER BY c.course_code
        ");
        $stmt->execute([$student_id]);
        $enrolled_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_units = 0;
        foreach ($enrolled_subjects as $sub) {
            $total_units += floatval($sub['credit_units']);
        }

        // --- INCLUDE TCPDF ---
        require_once __DIR__ . '/vendor/autoload.php';
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        // Document Info
        $pdf->SetCreator('DHVSU E-RIS');
        $pdf->SetAuthor('DHVSU');
        $pdf->SetTitle('Pre-Registration Form');
        $pdf->SetSubject('Enrollment Form');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 12, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        // --- HEADER ---
        $logoPath = __DIR__ . '/assets/img/vid.jpg';
        $logoHTML = file_exists($logoPath)
            ? '<img src="' . $logoPath . '" width="65" height="65">'
            : '';

        $headerHTML = '
        <table width="100%" border="0" cellspacing="0" cellpadding="2" >
          <tr>
            <td width="10%"></td>
            <td width="20%" valign="middle" style="padding-right: 2px; text-align:right;" >
                ' . $logoHTML . '
            </td>
            <td width="50%" align="center" valign="middle">
                <span style="font-size:8pt;">Republic of the Philippines</span><br>
                <span style="font-size:10pt; font-weight:bold;">DON HONORIO VENTURA STATE UNIVERSITY</span><br>
                <span style="font-size:8pt;">Bacolor, Pampanga</span><br><br>
                <span style="font-size:10pt; font-weight:bold;">PRE-REGISTRATION FORM</span><br>
                <span style="font-size:10pt; font-weight:bold;"><u>'. $semester_str .'</u> SEMESTER, ACADEMIC YEAR <u>'. $academic_year .'</u></span>
            </td>
            <td width="13%"></td>
          </tr>
        </table>
        ';
        $pdf->writeHTML($headerHTML, true, false, true, false, '');

        // --- DEMOGRAPHIC INFORMATION ---
        $demographicHTML = '
        <style>
        td.label {font-weight:bold; text-align:center; border:.5px solid #000; padding:5px; font-size:10px;}
        td.value {border:.5px solid #000; text-align:center; padding:8px; font-size:10px; line-height: 2;}
        .small-label {font-size:8.5px; font-style:italic; color:#333;}
        .section-title {font-weight:bold; font-size:10.5px; padding:10px 0px 4px 0px; border-left:.5px solid #000; border-right:.5px solid #000; border-top:none; border-bottom:none;}
        </style>

        <div style="font-weight:bold; font-size:11px; margin-bottom:8px;">I. DEMOGRAPHIC INFORMATION</div>

        <table width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
          <!-- Student Info Row -->
          <tr>
            <td class="label" width="33%">Student Number</td>
            <td class="label" width="50%">Program of Study and Major</td>
            <td class="label" width="17%">Year Level</td>
          </tr>
          <tr>
            <td class="value"><u>' . htmlspecialchars($student['student_id']) . '</u></td>
            <td class="value"><u>' . htmlspecialchars($student['major']) . '</u></td>
            <td class="value"><u>' . htmlspecialchars($student['year_level']) . '</u></td>
          </tr>
          
          <!-- Name Section -->
          <tr>
            <td colspan="3" class="section-title" style="border-left:.5px solid #000; border-right:.5px solid #000;"> Name of Student</td>
          </tr>
          <tr>
            <td width="33%" height ="20"style="border:.5px solid #000; text-align:center; padding:8px; ">
              <u><br>' . htmlspecialchars($student['last_name']) . '</u><br><span class="small-label">Family Name</span>
            </td>
            <td width="34%" height ="20"style="border:.5px solid #000; text-align:center; padding:8px;">
              <u><br>' . htmlspecialchars($student['first_name']) . '</u><br><span class="small-label">Given Name</span>
            </td>
            <td width="33%" height ="20"style="border:.5px solid #000; text-align:center; padding:8px;">
              <u><br>' . (empty($student['middle_name']) ? 'N/A' : htmlspecialchars($student['middle_name'])) . '</u><br><span class="small-label">Middle Name</span>
            </td>
          </tr>
          
          <!-- Contact Details Section -->
          <tr>
            <td colspan="3" class="section-title" style="border-left:.5px solid #000; border-right:.5px solid #000;"> Contact Details</td>
          </tr>
          <tr>
            <td width="33%" height ="20" style="border:.5px solid #000; text-align:center; padding:8px;">
              <u><br>' . htmlspecialchars($student['mobile_number']) . '</u><br><span class="small-label">Primary Mobile Number</span>
            </td>
            <td width="34%" height ="20" style="border:.5px solid #000; text-align:center; padding:8px;">
              <u><br>' . (empty($student['secondary_mobile_number']) ? 'N/A' : htmlspecialchars($student['secondary_mobile_number'])) . '</u><br><span class="small-label">Secondary Mobile Number</span>
            </td>
            <td width="33%" height ="20" style="border:.5px solid #000; text-align:center; padding:8px;">
              <u><br>' . htmlspecialchars($student['email']) . '</u><br><span class="small-label">Active Email Address</span>
            </td>
          </tr>
        </table>
        ';
        $pdf->writeHTML($demographicHTML, true, false, true, false, '');

        // --- ENROLLMENT DETAILS ---
        $pdf->Ln(0); 
        $enrollHTML = '
        <br>
        <div style="font-weight:bold; font-size:11px; margin-bottom:2px;">II. ENROLLMENT DETAILS</div>
        <table cellpadding="2" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">
          <tr>
            <th style="width:15%; border:.5px solid #000; background-color:#f0f0f0; text-align:center; padding:5px;"><b>Course Code</b></th>
            <th style="width:35%; border:.5px solid #000; background-color:#f0f0f0; text-align:center; padding:5px;"><b>Course Title</b></th>
            <th style="width:10%; border:.5px solid #000; background-color:#f0f0f0; text-align:center; padding:5px;"><b>Units</b></th>
            <th style="width:15%; border:.5px solid #000; background-color:#f0f0f0; text-align:center; padding:5px;"><b>Section</b></th>
            <th style="width:25%; border:.5px solid #000; background-color:#f0f0f0; text-align:center; padding:5px;"><b>Schedule/ Room</b></th>
          </tr>';

        if (count($enrolled_subjects) > 0) {
            foreach ($enrolled_subjects as $sub) {
                $sched = str_replace(' & ', '<br>', htmlspecialchars($sub['schedule']));
                $enrollHTML .= '
                <tr>
                    <td style="border:.5px solid #000; padding:5px; text-align:center;">' . htmlspecialchars($sub['course_code']) . '</td>
                    <td style="border:.5px solid #000; padding:5px;">   ' . htmlspecialchars($sub['course_title']) . '</td>
                    <td style="border:.5px solid #000; padding:5px; text-align:center;">' . htmlspecialchars($sub['credit_units']) . '</td>
                    <td style="border:.5px solid #000; padding:5px; text-align:center;">' . htmlspecialchars($sub['section_name']) . '</td>
                    <td style="border:.5px solid #000; padding:5px;">' . $sched . '</td>
                </tr>';
            }
        } else {
            $enrollHTML .= '<tr><td colspan="5" style="border:.5px solid #000; padding:8px; text-align:center;">No enrolled subjects found.</td></tr>';
        }

        $enrollHTML .= '
          <tr>
            <td colspan="2" style="border:.5px solid #000; padding:6px; text-align:right;"><b>Total Units  </b></td>
            <td style="border:.5px solid #000; padding:6px; text-align:center;"><b>' . $total_units . '</b></td>
            <td colspan="2" style="border:.5px solid #000;"></td>
          </tr>
        </table>
        ';
        $pdf->writeHTML($enrollHTML, true, false, true, false, '');

        // Check available space before adding declaration section
        $current_y = $pdf->GetY();
        $page_height = 297; // A4 height in mm
        $bottom_margin = 15;

        // If less than 120mm space left, add new page (adjust this value as needed)
        if (($page_height - $current_y) < 80) {
            $pdf->AddPage();
        }

        // --- DECLARATION SECTION ---
        $declHTML = '
        <div style="font-size:9px; text-align:justify; margin-top:2px;">
          <table cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td width="95%" style="font-size:9px;"><strong>By signing this document:</strong></td>
              <td width="5%" style="font-size:9px;"></td>
            </tr>
            <tr>
              <td width="3%" style="font-size:9px; vertical-align:top;">1.</td>
              <td width="97%" style="font-size:9px;">I fully understand and agree that the information above are true and correct; and that any false statement on this document (as well as on the supporting documents) constitutes a perjury and could result in denial of the registration and disciplinary proceedings with sanction as per the Don Honorio Ventura State University (the "University") policies;</td>
            </tr>
            <tr>
              <td width="3%" style="font-size:9px; vertical-align:top;">2.</td>
              <td width="97%" style="font-size:9px;">I am fully aware and understand that registration/enrollment in any course for the abovementioned academic period is allowed only upon passing the pre-requisite(s) of the said course (if any); a course registered/enrolled in violation of this rule will not be given any credit regardless of the grade obtained;</td>
            </tr>
            <tr>
              <td width="3%" style="font-size:9px; vertical-align:top;">3.</td>
              <td width="97%" style="font-size:9px;">I authorize the University to collect and process any information declared herein with utmost confidentiality; and</td>
            </tr>
            <tr>
              <td width="3%" style="font-size:9px; vertical-align:top;">4.</td>
              <td width="97%" style="font-size:9px;">I allow the University to disclose the collected information to its affiliates and lawful third parties for legitimate purposes only.</td>
            </tr>
          </table>
        </div>

        <!-- Student signature table -->
        <table width="100%" border="1" cellspacing="0" cellpadding="4" style="font-size:9px; margin-top:10px; border-collapse:collapse;">
          <tr>
            <td width="70%" align="center"><b>Signature over printed name of Student</b></td>
            <td width="30%" align="center"><b>Date signed by the Student</b></td>
          </tr>
          <tr>
            <td align="center" style="padding: 15mm 0 8px 0; height: 5mm; vertical-align: bottom;">
                <br><br><br> <!-- Creates space for signature -->
                ' . htmlspecialchars($student['last_name'] . ", " . $student['first_name'] . " " . (empty($student['middle_name']) ? '' : substr($student['middle_name'], 0, 1) . ".")) . '
            <br></td>
            <td align="center" style="padding: 8px 0;"><br><br><br>' . $submission_date . '</td>
        </tr>
        </table>
        ';

        $pdf->writeHTML($declHTML, true, false, true, false, '');

        // Get the Y position after writing the student signature section
        $after_signature_section = $pdf->GetY();

        // Calculate the position for the signature - it should be in the student signature cell
        // The signature cell starts approximately 25mm above the current Y position
        $signature_cell_top = $after_signature_section - 30; // Adjust this based on actual cell height
        $signature_y_position = $signature_cell_top + 1; // Position 5mm from top of signature cell

        // Check if student has a signature and add it to the PDF
        $stmt = $conn->prepare("SELECT signature_form FROM files WHERE student_id = ? AND signature_form IS NOT NULL");
        $stmt->execute([$student_id]);
        $signature = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($signature && file_exists($signature['signature_form'])) {
            // Position the signature exactly on top of the printed name in the signature cell
            $signature_x = 50; // Left margin - adjust as needed
            $signature_width = 80; // Width of signature image
            $signature_height = 20; // Height of signature image
            
            $pdf->Image($signature['signature_form'], $signature_x, $signature_y_position, $signature_width, $signature_height);
        }

        // Continue with the rest of the PDF
        $personnelHTML = '
        <br>

        <!-- Personnel section header -->
        <div style="text-align:center; font-size:9px; padding:15px 0;">
          ------------------------------------------------------------<b>FOR DHVSU PERSONNEL ONLY</b>------------------------------------------------------------
        </div>
        <br>

        <!-- Recommending Approval -->
        <table width="100%" border="1" cellspacing="0" cellpadding="4" style="font-size:9px; border-collapse:collapse;">
          <tr>
            <td align="center" style="font-weight:bold;">Recommending Approval</td>
          </tr>
          <tr>
            <td>
              By signing this document, I, hereby, declare that I have fully assessed the status of the student and advised him/her with the appropriate courses to enroll in the academic period stated above.
              <br><br><br>
              <table width="100%" border="0" cellspacing="0" cellpadding="0" style="font-size:9px;">
                <tr>
                  <!-- Signature (70%) -->
                  <td width="65%" style="vertical-align:top; text-align:center;">
                    ___________________________________________________________<br>
                    <span style="font-size:8px;"><i>Signature over printed name of Program Chairperson</i></span>
                  </td>
                  <!-- Date (30%) -->
                  <td width="35%" style="vertical-align:top; text-align:center;">
                    _______________________________<br>
                    <span style="font-size:8px;"><i>Date recommended for approval</i></span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>

        <!-- Footer -->
        <div style="text-align:left; margin-top:5px; font-size:8px;">DHVSU-QSP-REG-001-FO001-R00</div>
        ';

        $pdf->writeHTML($personnelHTML, true, false, true, false, '');

        // Check available space before adding approved by section
        $current_y = $pdf->GetY();
        // If less than 40mm space left, add new page for approved by section
        if (($page_height - $current_y) < 40) {
            $pdf->AddPage();
        } else {
            // Add some space if there's enough room on the same page
            $pdf->Ln(10);
        }

        $approvedByHTML = '
        <!-- Approved by -->
        <table width="100%" border="1" cellspacing="0" cellpadding="4" style="font-size:9px; border-collapse:collapse;">
          <tr>
            <td width="65%" align="center"><b>Approved by</b></td>
            <td width="35%" align="center"><b>Date approved</b></td>
          </tr>
          <tr>
            <td align="center" height="60">
              <br><br><br>______________________________________________________________<br>
              <span style="font-size:8px;"><i>Signature over printed name of College Dean</i></span>
            </td>
            <td align="center" height="60">
              <br><br><br>______________________________
            </td>
          </tr>
        </table>

        <!-- Footer for second page -->
        <div style="text-align:left; margin-top:10px; font-size:8px;">DHVSU-QSP-REG-001-FO001-R00</div>
        ';

        $pdf->writeHTML($approvedByHTML, true, false, true, false, '');

        // --- SAVE PDF silently without showing or echoing anything ---
        $outputDir = __DIR__ . '/uploads/advising_forms/';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $pdfFileName = 'advising_form_' . $student['student_id'] . '_' . date('Ymd_His') . '.pdf';
        $pdfFilePath = $outputDir . $pdfFileName;

        // Save PDF file
        $pdf->Output($pdfFilePath, 'F');

        // Save relative path to DB
        $relativePath = 'uploads/advising_forms/' . $pdfFileName;
        
        // Update submissions table with advising_form path
        $stmt = $conn->prepare("UPDATE submissions SET advising_form = ? WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$relativePath, $student_id]);
        
        // Check if update was successful
        $affectedRows = $stmt->rowCount();
        
        $conn = null; // Close connection
        
        if ($affectedRows > 0) {
            return [
                'success' => true,
                'message' => 'PDF generated and saved successfully!',
                'file_path' => $relativePath
            ];
        } else {
            return [
                'success' => false,
                'message' => 'PDF generated but failed to update database.'
            ];
        }
        
    } catch (Exception $e) {
        // Log error and return failure
        error_log("PDF Generation Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error generating PDF: ' . $e->getMessage()
        ];
    }
}

// Usage example:
// $result = generateAndSaveAdvisingForm($student_id);
// if ($result['success']) {
//     echo "PDF saved: " . $result['file_path'];
// } else {
//     echo "Error: " . $result['message'];
// }
?>

// Handle signature form submission
signatureForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const file = signatureFile.files[0];
    if (!file) {
        alert('Please select a signature file');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'upload_signature');
    formData.append('signature', file);
    
    signatureResult.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Uploading...</div>';
    
    fetch('submit_form.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            signatureResult.innerHTML = '<div class="alert alert-success">' + result.message + '</div>';
            signatureFile.value = '';
            loadCurrentSignature();
        } else {
            signatureResult.innerHTML = '<div class="alert alert-danger">' + result.message + '</div>';
        }
    })
    .catch(error => {
        signatureResult.innerHTML = '<div class="alert alert-danger">Error uploading signature: ' + error.message + '</div>';
    });
});

  function removeTempEnrollment(sectionCourseId) {
    if (!confirm('Are you sure you want to remove this subject from your selection?')) return;

    const formData = new FormData();
    formData.append('action', 'remove_temp_enrollment');
    formData.append('section_course_id', sectionCourseId);

    fetch('submit_form.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            loadTempEnrolledSubjects();
            // Reload available subjects to show the removed subject again
            loadAvailableSubjects();
        } else {
            alert('Error: ' + result.message);
        }
    });
}

  // KEEP YOUR EXISTING EVALUATION CODE BELOW - DON'T CHANGE
  yearButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      yearButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentYear = btn.dataset.year;
      loadEvaluation(currentYear);
    });
  });

  function loadEvaluation(year) {
    fetch(`submit_form.php?action=fetch_evaluation&year=${year}`)
      .then(res => res.json())
      .then(data => {
        console.log('Full Response Data:', data);
        
        if (data.debug) {
          debugInfo.textContent = data.debug.join('\n');
        }

        const result = data.result || [];
        
        if (!result.length) {
          evaluationResults.innerHTML = '<p class="text-muted">No subjects found for this year.</p>';
          return;
        }

        let html = '<table class="table table-sm table-bordered table-hover evaluation-table"><thead class="table-light"><tr><th>Code</th><th>Title</th><th>Status</th></tr></thead><tbody>';

        let firstSemester = result.filter(sub => sub.semester == 1);
        let secondSemester = result.filter(sub => sub.semester == 2);

        // First Semester
        html += `<tr><td colspan="3" class="semester-header">1st Semester</td></tr>`;
        firstSemester.forEach(sub => {
          let statusClass = 'status-missing-prereq';
          let statusText = 'Prerequisite Required';

          if (sub.student_status === 'passed') {
            statusClass = 'status-passed';
            statusText = 'Passed';
          } else if (sub.student_status === 'dropped') {
            statusClass = 'status-dropped';
            statusText = 'Dropped';
          } else if (sub.student_status === 'failed') {
            statusClass = 'status-failed';
            statusText = 'Failed';
          } else if (sub.student_status === 'available') {
            statusClass = 'status-available';
            statusText = 'Available';
          } else if (sub.student_status === 'missing_prereq') {
            statusClass = 'status-missing-prereq';
            statusText = 'Prerequisite Required';
          } else if (sub.student_status === 'not_available') {
            statusClass = 'status-not-available';
            statusText = 'Not Available (Wrong Semester)';
          }

          html += `<tr class="${statusClass}">
                      <td>${sub.course_code}</td>
                      <td>
                        <div>${sub.course_title}</div>
                        ${sub.prerequisite_text ? `<div class="prerequisite-text">${sub.prerequisite_text}</div>` : ''}
                      </td>
                      <td>${statusText}</td>
                   </tr>`;
        });

        html += `<tr><td colspan="3" class="semester-divider"></td></tr>`;

        // Second Semester
        html += `<tr><td colspan="3" class="semester-header">2nd Semester</td></tr>`;
        secondSemester.forEach(sub => {
          let statusClass = 'status-missing-prereq';
          let statusText = 'Prerequisite Required';

          if (sub.student_status === 'passed') {
            statusClass = 'status-passed';
            statusText = 'Passed';
          } else if (sub.student_status === 'dropped') {
            statusClass = 'status-dropped';
            statusText = 'Dropped';
          } else if (sub.student_status === 'failed') {
            statusClass = 'status-failed';
            statusText = 'Failed';
          } else if (sub.student_status === 'available') {
            statusClass = 'status-available';
            statusText = 'Available';
          } else if (sub.student_status === 'missing_prereq') {
            statusClass = 'status-missing-prereq';
            statusText = 'Prerequisite Required';
          } else if (sub.student_status === 'not_available') {
            statusClass = 'status-not-available';
            statusText = 'Not Available (Wrong Semester)';
          }

          html += `<tr class="${statusClass}">
                      <td>${sub.course_code}</td>
                      <td>
                        <div>${sub.course_title}</div>
                        ${sub.prerequisite_text ? `<div class="prerequisite-text">${sub.prerequisite_text}</div>` : ''}
                      </td>
                      <td>${statusText}</td>
                   </tr>`;
        });

        html += '</tbody></table>';
        evaluationResults.innerHTML = html;
      })
      .catch(error => {
        console.error('Error loading evaluation:', error);
        evaluationResults.innerHTML = '<p class="text-danger">Error loading evaluation data.</p>';
        debugInfo.textContent = 'Error: ' + error.message;
      });
  }

  // Initialize
  loadCurrentSemester(); // FIX: Call this to load semester
  loadEvaluation(currentYear);
});
</script>
</body>
</html>