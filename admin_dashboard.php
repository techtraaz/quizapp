<?php
// Database connection
$conn = new mysqli('localhost', 'root', '2002', 'quiz_app');

// Ensure the 'position' column exists and add it if not


// Handle form submission for adding or editing questions and answers
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $question_text = $_POST['question_text'];
    $image_path = '';

    // Handle image upload
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($_FILES["image"]["name"]);
        move_uploaded_file($_FILES["image"]["tmp_name"], $target_file);
        $image_path = $target_file;
    }

    // Check if we are editing an existing question
    if (isset($_POST['question_id']) && !empty($_POST['question_id'])) {
        $question_id = $_POST['question_id'];

        // Update question in the database
        if ($image_path) {
            $stmt = $conn->prepare("UPDATE questions SET question_text = ?, question_image = ? WHERE id = ?");
            $stmt->bind_param("ssi", $question_text, $image_path, $question_id);
        } else {
            $stmt = $conn->prepare("UPDATE questions SET question_text = ? WHERE id = ?");
            $stmt->bind_param("si", $question_text, $question_id);
        }
        $stmt->execute();

        // Update answers in the database
        for ($i = 0; $i < 4; $i++) {
            $answer_text = $_POST['answers'][$i];
            $is_correct = isset($_POST['correct_answer']) && $_POST['correct_answer'] == $i ? 1 : 0;
            $answer_id = $_POST['answer_ids'][$i];
            $stmt = $conn->prepare("UPDATE answers SET answer_text = ?, is_correct = ? WHERE id = ?");
            $stmt->bind_param("sii", $answer_text, $is_correct, $answer_id);
            $stmt->execute();
        }

        echo "Question updated successfully!";
    } else {
        // Get the next position value
        $result = $conn->query("SELECT MAX(position) AS max_position FROM questions");
        $max_position = $result->fetch_assoc()['max_position'];
        $next_position = $max_position + 1;

        // Insert new question into the database
        $stmt = $conn->prepare("INSERT INTO questions (question_text, question_image, position) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $question_text, $image_path, $next_position);
        $stmt->execute();
        $question_id = $stmt->insert_id;

        // Insert answers into the database
        for ($i = 0; $i < 4; $i++) {
            $answer_text = $_POST['answers'][$i];
            $is_correct = isset($_POST['correct_answer']) && $_POST['correct_answer'] == $i ? 1 : 0;
            $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $question_id, $answer_text, $is_correct);
            $stmt->execute();
        }

        echo "Question added successfully!";
    }
}

// Handle deleting a question
if (isset($_GET['delete'])) {
    $question_id = $_GET['delete'];
    
    // Get the position of the question to be deleted
    $result = $conn->query("SELECT position FROM questions WHERE id = $question_id");
    $deleted_position = $result->fetch_assoc()['position'];

    // Delete related answers and the question itself
    $conn->query("DELETE FROM answers WHERE question_id = $question_id");
    $conn->query("DELETE FROM questions WHERE id = $question_id");

    // Update positions of remaining questions
    $conn->query("UPDATE questions SET position = position - 1 WHERE position > $deleted_position");

    echo "Question deleted successfully!";
}

// Fetch all questions
$questions = $conn->query("SELECT * FROM questions ORDER BY position");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
</head>
<body>
    <h1>Add/Edit Question</h1>
    <form action="admin_dashboard.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="question_id" id="question_id">

        <label for="question_text">Question:</label><br>
        <textarea name="question_text" id="question_text" required></textarea><br><br>

        <label for="image">Image (optional):</label><br>
        <input type="file" name="image" id="image"><br><br>

        <label>Answers:</label><br>
        <?php for ($i = 0; $i < 4; $i++): ?>
            <input type="text" name="answers[]" id="answer_<?php echo $i; ?>" required><br>
            <input type="hidden" name="answer_ids[]" id="answer_id_<?php echo $i; ?>">
        <?php endfor; ?><br>

        <label>Correct Answer:</label><br>
        <select name="correct_answer" id="correct_answer" required>
            <?php for ($i = 0; $i < 4; $i++): ?>
                <option value="<?php echo $i; ?>">Answer <?php echo $i + 1; ?></option>
            <?php endfor; ?>
        </select><br><br>

        <button type="submit">Save Question</button>
    </form>

    <h1>Existing Questions</h1>
    <table border="1">
        <tr>
            <th>Question</th>
            <th>Image</th>
            <th>Actions</th>
        </tr>
        <?php while ($question = $questions->fetch_assoc()): ?>
            <tr>
                <td><?php echo $question['question_text']; ?></td>
                <td><?php if ($question['question_image']): ?><img src="<?php echo $question['question_image']; ?>" alt="Question Image" style="width: 100px;"><?php endif; ?></td>
                <td>
                    <a href="admin_dashboard.php?edit=<?php echo $question['id']; ?>">Edit</a>
                    <a href="admin_dashboard.php?delete=<?php echo $question['id']; ?>" onclick="return confirm('Are you sure you want to delete this question?')">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>

    <?php
    // Handle editing a question
    if (isset($_GET['edit'])) {
        $question_id = $_GET['edit'];
        $question = $conn->query("SELECT * FROM questions WHERE id = $question_id")->fetch_assoc();
        $answers = $conn->query("SELECT * FROM answers WHERE question_id = $question_id")->fetch_all(MYSQLI_ASSOC);
    ?>
    <script>
        document.getElementById('question_id').value = '<?php echo $question['id']; ?>';
        document.getElementById('question_text').value = '<?php echo addslashes($question['question_text']); ?>';
        <?php if ($question['question_image']): ?>
        document.getElementById('image').value = '<?php echo $question['question_image']; ?>';
        <?php endif; ?>
        <?php foreach ($answers as $i => $answer): ?>
        document.getElementById('answer_<?php echo $i; ?>').value = '<?php echo addslashes($answer['answer_text']); ?>';
        document.getElementById('answer_id_<?php echo $i; ?>').value = '<?php echo $answer['id']; ?>';
        <?php if ($answer['is_correct']): ?>
        document.getElementById('correct_answer').value = '<?php echo $i; ?>';
        <?php endif; ?>
        <?php endforeach; ?>
    </script>
    <?php } ?>
</body>
</html>
