<?php

$host = "0.0.0.0";
$port = 3000;
set_time_limit(0);
$socket = socket_create(AF_INET, SOCK_STREAM, 0) or die("Could not create socket\n");
$result = socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
$result = socket_bind($socket, $host, $port) or die("Could not bind to socket\n");
$result = socket_listen($socket) or die("Could not set up socket listener\n");

$db = new PDO('sqlite:/var/www/tcpserver/users.db');

while(true) {
  $spawn = socket_accept($socket) or die("Could not accept incoming connection\n");
  $string = utf8_encode(socket_read($spawn, 1024));

  $items = explode(',' , $string);

  if ($items[0] == 'deactivate') {
    $address = $items[1];
    $updateQuery = $db->prepare("UPDATE users SET address = :newAddress, isActivate = :isActivate WHERE address = :address");
    $updateQuery->bindValue(":newAddress", "");
    $updateQuery->bindValue(":isActivate", 0);
    $updateQuery->bindValue(":address", $address );
    if ($updateQuery->execute()) {
      $byteArray = "Deactivate";
      socket_write($spawn, $byteArray);
    }
  }
  else if($items[0] == "generatecode") {
    $userName = $items[1];
    $expireDate = $items[2];

    $characters = 'gmnvl67ABCIJKh90ij81pq2oksGH345efdrNOcQPabYuWXwSUVTZtRxDEFyzLM';
    $randomString = '';
    for ($i = 0; $i < 12; $i++) {
        $index = rand(0, strlen($characters) - 1);
        $randomString .= $characters[$index];
    }

    $query = $db->prepare("SELECT COUNT(*) AS count FROM users WHERE name = :name");
    $query->bindValue(":name", $userName);

    $result = $query->execute();
    $count = $query->fetchColumn();

    if($count == 0)
    {
        $stmt = $db->prepare('INSERT INTO users (name, code , address , isActivate , expireDate) VALUES (:name, :code , :address , :isActivate , :expireDate)');
        $stmt->bindValue(':name', $userName);
        $stmt->bindValue(':code', $randomString);
        $stmt->bindValue(':address', "");
        $stmt->bindValue(':isActivate', 0);
        $stmt->bindValue(':expireDate', $expireDate);
        $stmt->execute();
    }
    else
    {
        $stmt = $db->prepare('UPDATE users SET code = :code, expireDate = :expireDate WHERE name = :name');
        $stmt->bindValue(':code', $randomString);
        $stmt->bindValue(':expireDate', $expireDate);
        $stmt->bindValue(':name', $userName);
        $stmt->execute();
    }

    socket_write($spawn, $randomString);

  }
  else if ($items[0] == 'logincheck') {
    $address = $items[1];
    $activate = 1;

    $query = $db->prepare("SELECT COUNT(*) AS count FROM users WHERE address = :address AND isActivate = :activate");
    $query->bindValue(":address", $address);
    $query->bindValue(":activate", 1);

    $result = $query->execute();
    $count = $query->fetchColumn();
    if($count == 0)
    {
        $byteArray = "nothing";
        socket_write($spawn, $byteArray);
    }
    else {

        $query = $db->prepare("SELECT * FROM users WHERE address = :address AND isActivate = :activate");
        $query->bindValue(":address", $address);
        $query->bindValue(":activate", 1);
        $query->execute();
        $row = $query->fetch(PDO::FETCH_ASSOC);

        $byteArray = "Activate".','.$row['name'].','.$row['expireDate'];
        socket_write($spawn, $byteArray);
    }
  }
  else {
    $code = $items[0];
    $address = $items[1];
    $query = $db->prepare("SELECT * FROM users WHERE code = :code ");
    $query->bindValue(":code" , $code);
    $query->execute();
    $flag = 0;
    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $flag = 1;
        $value = $row['isActivate'];
        if ($value == 1) {
            $byteArray = "Already";
            socket_write($spawn, $byteArray);
        } else if ($value == 0) {

            $query = $db->prepare("SELECT * FROM users WHERE code = :code");
            $query->bindValue(":code", $code);
            $query->execute();
            $row = $query->fetch(PDO::FETCH_ASSOC);

            $currentDate = date('Y-m-d');
            $formattedDate = date('Y-m-d', strtotime($row['expireDate']));
            if($formattedDate >= $currentDate){
                $updateQuery = $db->prepare("UPDATE users SET address = :address , isActivate = :isActivate "
                    . "WHERE code = :code");
                $updateQuery->bindValue(":address", $address);
                $updateQuery->bindValue(":isActivate", 1);
                $updateQuery->bindValue(":code", $code);

                if ($updateQuery->execute()) {
                    $byteArray = "Activate".','.$row['name'].','.$row['expireDate'];
                    socket_write($spawn, $byteArray);
                }
            }
            else{
                $byteArray = "expiredatepast";
                socket_write($spawn, $byteArray);
            }
        }
    }

    if ($flag == 0) {
        $byteArray = "Wrong";
        socket_write($spawn, $byteArray , strlen($byteArray));
    }
  }
  $output = $string . "\n";
  socket_close($spawn);
}

$db = null;

?>
