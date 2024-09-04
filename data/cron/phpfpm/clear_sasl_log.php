<?php
require_once "/web/inc/vars.inc.php";
if (file_exists('/web/inc/vars.local.inc.php')) {
  include_once('/web/inc/vars.local.inc.php');
}
ini_set('error_reporting', 0);
// Init database
//$dsn = $database_type . ':host=' . $database_host . ';dbname=' . $database_name;
$dsn = $database_type . ":unix_socket=" . $database_sock . ";dbname=" . $database_name;
$opt = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
  $pdo = new PDO($dsn, $database_user, $database_pass, $opt);
}
catch (PDOException $e) {
  echo($e->getMessage() . PHP_EOL);
  exit;
}


try {
  $dateThreshold = new DateTime();
  $dateThreshold->modify('-31 days');
  $dateThresholdFormatted = $dateThreshold->format('Y-m-d H:i:s');

  $batchSize = 1000;
  $lastProcessedDatetime = null;
  $lastFetchedRows = 0;
  $loopCounter = 0;
  $rowCounter = 0;
  $clearedRowCounter = 0;

  do {
    $loopCounter++;
    echo("Processing batch $loopCounter\n");

    $stmt = $pdo->prepare("
      SELECT service, real_rip, username, datetime
      FROM sasl_log
      WHERE datetime < :dateThreshold
      AND (:lastProcessedDatetime IS NULL OR datetime >= :lastProcessedDatetime2)
      ORDER BY datetime ASC
      LIMIT :limit
    ");
    $stmt->execute(array(
      ':dateThreshold' => $dateThresholdFormatted,
      ':lastProcessedDatetime' => $lastProcessedDatetime,
      ':lastProcessedDatetime2' => $lastProcessedDatetime,
      ':limit' => $batchSize
    ));

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $rowCount = count($rows);
    $rowCounter += $rowCount;

    echo("Fetched $rowCount rows (total of $rowCounter)\n");

    foreach ($rows as $row) {
      $stmt = $pdo->prepare("
        SELECT MAX(datetime) as max_date
        FROM sasl_log
        WHERE datetime < :dateThreshold AND service = :service AND username = :username
      ");
      $stmt->execute(array(
        ':dateThreshold' => $dateThresholdFormatted,
        ':service' => $row['service'],
        ':username' => $row['username']
      ));
      $subrow = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($row['datetime'] < $subrow['max_date']) {
        $stmt = $pdo->prepare("
          DELETE FROM sasl_log
          WHERE username = :username AND service = :service AND datetime = :datetime
        ");
        $stmt->execute(array(
          ':username' => $row['username'],
          ':service' => $row['service'],
          ':datetime' => $row['datetime']
        ));

        $clearedRowCounter++;
      }
    }

    if ($lastFetchedRows == $rowCount && $rowCount != $batchSize) {
      $rowCount = 0;
    }

    // Update last processed datetime
    if ($rowCount > 0) {
      $lastProcessedDatetime = $rows[$rowCount - 1]['datetime'];
      $lastFetchedRows = $rowCount;
    }

  } while ($rowCount > 0);
}
catch (PDOException $e) {
  echo($e->getMessage() . PHP_EOL);
  exit;
}

echo("Succesfully cleared $clearedRowCounter rows of $rowCounter rows");
