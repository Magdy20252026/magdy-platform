<?php
// students/inc/access_control.php
// ✅ Rules:
// 1) If student enrolled in course => all lectures open automatically.
// 2) If not enrolled in course => lecture open only if enrolled in that lecture.
// 3) If course.access_type = free => open.

require __DIR__ . '/../../admin/inc/db.php';

function student_has_course_access(PDO $pdo, int $studentId, int $courseId): bool {
  if ($studentId <= 0 || $courseId <= 0) return false;

  // free course?
  $stmt = $pdo->prepare("SELECT access_type FROM courses WHERE id=? LIMIT 1");
  $stmt->execute([$courseId]);
  $accessType = (string)($stmt->fetchColumn() ?: '');
  if ($accessType === 'free') return true;

  // enrolled in course?
  $stmt = $pdo->prepare("SELECT 1 FROM student_course_enrollments WHERE student_id=? AND course_id=? LIMIT 1");
  $stmt->execute([$studentId, $courseId]);
  return (bool)$stmt->fetchColumn();
}

function lecture_get_course_id(PDO $pdo, int $lectureId): int {
  if ($lectureId <= 0) return 0;
  $stmt = $pdo->prepare("SELECT course_id FROM lectures WHERE id=? LIMIT 1");
  $stmt->execute([$lectureId]);
  return (int)($stmt->fetchColumn() ?: 0);
}

function student_has_lecture_access(PDO $pdo, int $studentId, int $lectureId): bool {
  if ($studentId <= 0 || $lectureId <= 0) return false;

  $courseId = lecture_get_course_id($pdo, $lectureId);
  if ($courseId <= 0) return false;

  // ✅ IMPORTANT: course access opens all lectures
  if (student_has_course_access($pdo, $studentId, $courseId)) return true;

  // lecture enrollment opens single lecture
  $stmt = $pdo->prepare("SELECT 1 FROM student_lecture_enrollments WHERE student_id=? AND lecture_id=? LIMIT 1");
  $stmt->execute([$studentId, $lectureId]);
  return (bool)$stmt->fetchColumn();
}