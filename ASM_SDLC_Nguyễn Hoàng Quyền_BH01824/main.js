// Sidebar toggle
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle
    const toggleSidebar = document.getElementById('toggleSidebar');
    if (toggleSidebar) {
        toggleSidebar.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
        });
    }

    // Tab navigation
    document.querySelectorAll('[data-tab]').forEach(tab => {
        tab.addEventListener('click', function() {
            const target = this.getAttribute('data-tab');
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(target + '-tab').classList.add('active');
            document.querySelectorAll('.active-tab').forEach(activeTab => {
                activeTab.classList.remove('active-tab');
            });
            this.classList.add('active-tab');
            // Update page title
            const pageTitle = document.getElementById('pageTitle');
            if (pageTitle && this.querySelector('.sidebar-text')) {
                pageTitle.textContent = this.querySelector('.sidebar-text').textContent;
            }
        });
    });

    // Quiz modals
    window.openCreateQuizModal = function() {
        document.getElementById('createQuizModal').classList.remove('hidden');
    }
    window.closeCreateQuizModal = function() {
        document.getElementById('createQuizModal').classList.add('hidden');
    }
    window.openEditQuizModal = function(quiz) {
        document.getElementById('edit_quiz_id').value = quiz.id;
        document.getElementById('edit_course_id').value = quiz.course_id;
        document.getElementById('edit_title').value = quiz.title;
        document.getElementById('edit_description').value = quiz.description;
        document.getElementById('edit_duration').value = quiz.duration;
        document.getElementById('edit_start_date').value = quiz.quiz_date.replace(' ', 'T');
        document.getElementById('edit_max_score').value = quiz.total_marks;
        document.getElementById('edit_status').value = quiz.status;
        document.getElementById('editQuizModal').classList.remove('hidden');
    }
    window.closeEditQuizModal = function() {
        document.getElementById('editQuizModal').classList.add('hidden');
    }
    window.viewQuiz = function(quiz) {
        window.location.href = '?type=quizzes&quiz_id=' + quiz.id;
    }
    window.startQuiz = function(quizId) {
        window.location.href = '?type=quizzes&quiz_id=' + quizId;
    }
    window.deleteQuiz = function(quizId) {
        if (confirm('Bạn có chắc chắn muốn xóa bài kiểm tra này?')) {
            document.getElementById('delete_quiz_id').value = quizId;
            document.getElementById('deleteQuizForm').submit();
        }
    }

    // Course modals
    window.openAddCourseModal = function() {
        const modal = document.getElementById('addCourseModal');
        if (modal) modal.classList.remove('hidden');
    }
    window.closeAddCourseModal = function() {
        const modal = document.getElementById('addCourseModal');
        if (modal) modal.classList.add('hidden');
    }
    window.openEditCourseModal = function(course) {
        const modal = document.getElementById('editCourseModal');
        if (!modal) return;
        const idInput = document.getElementById('edit_course_id');
        const nameInput = document.getElementById('edit_course_name');
        const descInput = document.getElementById('edit_course_description');
        const statusSelect = document.getElementById('edit_course_status');
        if (idInput) idInput.value = course.id;
        if (nameInput) nameInput.value = course.name || '';
        if (descInput) descInput.value = course.description || '';
        if (statusSelect && course.status) statusSelect.value = course.status;
        modal.classList.remove('hidden');
    }
    window.closeEditCourseModal = function() {
        const modal = document.getElementById('editCourseModal');
        if (modal) modal.classList.add('hidden');
    }

    // Grades modals
    window.openEditGradeModal = function(type, id, score) {
        const modal = document.getElementById('editGradeModal');
        if (!modal) return;
        const actionInput = document.getElementById('edit_action');
        const enrollmentIdInput = document.getElementById('edit_enrollment_id');
        const submissionIdInput = document.getElementById('edit_submission_id');
        const resultIdInput = document.getElementById('edit_result_id');
        const gradeInput = document.getElementById('edit_grade');
        if (gradeInput) gradeInput.value = score != null ? score : '';
        if (enrollmentIdInput) enrollmentIdInput.value = '';
        if (submissionIdInput) submissionIdInput.value = '';
        if (resultIdInput) resultIdInput.value = '';
        if (actionInput) {
            if (type === 'enrollment') {
                actionInput.value = 'update_enrollment_grade';
                if (enrollmentIdInput) enrollmentIdInput.value = id;
            } else if (type === 'assignment') {
                actionInput.value = 'update_assignment_grade';
                if (submissionIdInput) submissionIdInput.value = id;
            } else if (type === 'quiz') {
                actionInput.value = 'update_quiz_grade';
                if (resultIdInput) resultIdInput.value = id;
            }
        }
        modal.classList.remove('hidden');
    }
    window.closeEditGradeModal = function() {
        const modal = document.getElementById('editGradeModal');
        if (modal) modal.classList.add('hidden');
    }

    window.deleteCourse = function(courseId) {
        if (confirm('Bạn có chắc chắn muốn xóa khóa học này?')) {
            const idInput = document.getElementById('delete_course_id');
            const form = document.getElementById('deleteCourseForm');
            if (idInput && form) {
                idInput.value = courseId;
                form.submit();
            }
        }
    }

    // Timer functionality for quizzes
    let startTime = Date.now();
    let timerInterval;
    function startTimer(duration) {
        let timer = duration;
        const timerElement = document.getElementById('timer');
        if (timerElement) {
            timerInterval = setInterval(function () {
                const minutes = parseInt(timer / 60, 10);
                const seconds = parseInt(timer % 60, 10);
                timerElement.textContent = minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
                if (--timer < 0) {
                    clearInterval(timerInterval);
                    document.getElementById('quizForm').submit();
                }
            }, 1000);
        }
    }
    const quizForm = document.getElementById('quizForm');
    if (quizForm) {
        if (typeof QUIZ_DURATION !== 'undefined') {
            startTimer(QUIZ_DURATION * 60);
        }
        quizForm.addEventListener('submit', function() {
            const timeTakenEl = document.getElementById('timeTaken');
            if (timeTakenEl) {
                const timeTaken = Math.floor((Date.now() - startTime) / 1000);
                timeTakenEl.value = timeTaken;
            }
        });
    }

    // Assignment modals (inline forms use these)
    window.openCreateAssignmentModal = function() {
        const modal = document.getElementById('createAssignmentModal');
        if (modal) modal.classList.remove('hidden');
    }
    window.closeCreateAssignmentModal = function() {
        const modal = document.getElementById('createAssignmentModal');
        if (modal) modal.classList.add('hidden');
    }
    window.openEditAssignmentModal = function(assignment) {
        const modal = document.getElementById('editAssignmentModal');
        if (!modal) return;
        document.getElementById('edit_assignment_id').value = assignment.id;
        document.getElementById('edit_course_id').value = assignment.course_id;
        document.getElementById('edit_title').value = assignment.title;
        document.getElementById('edit_description').value = assignment.description || '';
        document.getElementById('edit_due_date').value = (assignment.due_date || '').replace(' ', 'T');
        if (document.getElementById('edit_max_score')) document.getElementById('edit_max_score').value = assignment.total_points || '';
        if (document.getElementById('edit_status') && assignment.status) document.getElementById('edit_status').value = assignment.status;
        modal.classList.remove('hidden');
    }
    window.closeEditAssignmentModal = function() {
        const modal = document.getElementById('editAssignmentModal');
        if (modal) modal.classList.add('hidden');
    }
    window.openSubmitModal = function(assignmentId) {
        const modal = document.getElementById('submitAssignmentModal');
        if (!modal) return;
        document.getElementById('submit_assignment_id').value = assignmentId;
        modal.classList.remove('hidden');
    }
    window.closeSubmitModal = function() {
        const modal = document.getElementById('submitAssignmentModal');
        if (modal) modal.classList.add('hidden');
    }
    window.viewAssignment = function(assignment) {
        const modal = document.getElementById('viewAssignmentModal');
        if (!modal) return;
        document.getElementById('viewTitle').textContent = assignment.title;
        document.getElementById('viewCourse').textContent = 'Khóa học: ' + assignment.course_name;
        document.getElementById('viewDueDate').textContent = 'Hạn nộp: ' + assignment.due_date;
        document.getElementById('viewDescription').textContent = assignment.description || '';
        modal.classList.remove('hidden');
    }
    window.closeViewModal = function() {
        const modal = document.getElementById('viewAssignmentModal');
        if (modal) modal.classList.add('hidden');
    }
    window.deleteAssignment = function(assignmentId) {
        if (confirm('Bạn có chắc chắn muốn xóa bài tập này?')) {
            document.getElementById('delete_assignment_id').value = assignmentId;
            document.getElementById('deleteAssignmentForm').submit();
        }
    }
});