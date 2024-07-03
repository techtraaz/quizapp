<?php
session_start();
$conn = new mysqli('localhost', 'root', '2002', 'quiz_app');

// Initialize session answers array if not set
if (!isset($_SESSION['answers'])) {
    $_SESSION['answers'] = array();
}

// Determine the current question
if (isset($_GET['question'])) {
    $current_question = intval($_GET['question']);
} else {
    $current_question = 1;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $question_id = $_POST['question_id'];
    $answer_id = $_POST['answer_id'];
    $_SESSION['answers'][$question_id] = $answer_id;

    // Handle navigation
    if (isset($_POST['next'])) {
        $current_question++;
    } elseif (isset($_POST['prev'])) {
        $current_question--;
    } elseif (isset($_POST['submit'])) {
        // Calculate results
        $score = 0;
        foreach ($_SESSION['answers'] as $q_id => $a_id) {
            $result = $conn->query("SELECT is_correct FROM answers WHERE id = $a_id AND question_id = $q_id");
            if ($result->fetch_assoc()['is_correct']) {
                $score++;
            }
        }
        echo "Your score: $score/10";
        session_destroy();
        exit;
    }
}

// Fetch the current question and answers
$question_query = "SELECT * FROM questions WHERE position = $current_question";
$question_result = $conn->query($question_query);
$question = $question_result->fetch_assoc();

$idd = $_GET['question'];
echo $idd;

$answers_query = "SELECT * FROM answers WHERE question_id = $idd";
$answers_result = $conn->query($answers_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quiz</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="sidebar">
        <?php for ($i = 1; $i <= 10; $i++): ?>
            <a href="?question=<?php echo $i; ?>" <?php if ($i == $current_question) echo 'class="active"'; ?>>Question <?php echo $i; ?></a>
        <?php endfor; ?>
    </div>

    <div class="content">
        <form method="post" action="quiz.php">
            <input type="hidden" name="question_id" value="<?php echo $current_question; ?>">

            <div class="question">
                <p><?php echo $question['question_text']; ?></p>
                <?php if (!empty($question['question_image'])): ?>
                    <img src="<?php echo $question['question_image']; ?>" alt="Question Image"><br>
                <?php endif; ?>
                <?php while ($answer = $answers_result->fetch_assoc()): ?>
                    <label>
                        <input type="radio" name="answer_id" value="<?php echo $answer['id']; ?>" <?php if (isset($_SESSION['answers'][$current_question]) && $_SESSION['answers'][$current_question] == $answer['id']) echo 'checked'; ?> required>
                        <?php echo $answer['answer_text']; ?>
                    </label><br>
                <?php endwhile; ?>
            </div>

            <div class="navigation">
                <?php if ($current_question > 1): ?>
                    <button type="submit" name="prev">Previous</button>
                <?php endif; ?>
                <?php if ($current_question < 10): ?>
                    <button type="submit" name="next">Next</button>
                <?php else: ?>
                    <button type="submit" name="submit">Submit</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</body>
</html>
