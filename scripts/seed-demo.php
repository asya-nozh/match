<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../php/config.php';
if ((int)$conn->query('SELECT COUNT(*) FROM users')->fetch_row()[0] !== 0) {
    fwrite(STDERR, "Demo seed requires an empty users table. Nothing changed.\n"); exit(1);
}
$password = getenv('DEMO_PASSWORD') ?: bin2hex(random_bytes(10));
$hash = password_hash($password, PASSWORD_DEFAULT);
$conn->begin_transaction();
try {
    $people = [
        ['demo_user','participant','Алексей','mikhail','Люблю игры, музыку и прогулки.'],
        ['demo_anna','participant','Анна','anya','Люблю книги, искусство и кофе.'],
        ['demo_liza','participant','Лиза','liza','Ищу друзей для прогулок и выставок.'],
        ['demo_admin','admin','Администратор','artem','Демонстрационный аккаунт.'],
        ['demo_moderator','moderator','Модератор','kirill','Демонстрационный аккаунт.'],
    ];
    $ids = [];
    foreach ($people as [$login,$role,$name,$avatar,$about]) {
        $roleStmt = $conn->prepare('SELECT id FROM roles WHERE slug=?');
        $roleStmt->bind_param('s',$role); $roleStmt->execute();
        $roleId = (int)$roleStmt->get_result()->fetch_row()[0];
        $email = $login . '@example.test';
        $stmt=$conn->prepare('INSERT INTO users(role_id,username,email,password_hash,email_verified_at,is_verified) VALUES(?,?,?,?,NOW(),1)');
        $stmt->bind_param('isss',$roleId,$login,$email,$hash); $stmt->execute();
        $id=$conn->insert_id; $ids[]=$id;
        $path='images/demo-avatars/'.$avatar.'.svg';
        $stmt=$conn->prepare("INSERT INTO user_profiles(user_id,display_name,birth_date,city_id,about,interests_text,avatar_path,completed_at) VALUES(?,?,'2000-01-01',1,?,?,?,NOW())");
        $stmt->bind_param('issss',$id,$name,$about,$about,$path); $stmt->execute();
        $conn->query("INSERT INTO user_interests(user_id,interest_id) SELECT $id,id FROM interests LIMIT 3");
    }
    [$first,$second]=$ids;
    $conn->query("INSERT INTO matches(user1_id,user2_id) VALUES($first,$second)");
    $match=$conn->insert_id;
    $conn->query("INSERT INTO chats(match_id) VALUES($match)");
    $chat=$conn->insert_id;
    $stmt=$conn->prepare('INSERT INTO messages(chat_id,sender_id,message_text) VALUES(?,?,?)');
    $message='Привет! Это демонстрационный чат. Пойдём на выставку?';
    $stmt->bind_param('iis',$chat,$second,$message); $stmt->execute();
    $admin=$ids[3];
    $conn->query("INSERT INTO places(category_id,city_id,name,description,address,image_path,created_by_user_id) SELECT (SELECT MIN(id) FROM place_categories),1,'Демо-кафе','Пример места для дружеской встречи.','Учебный адрес, 1','images/coffee.png',$admin");
    $conn->commit();
    echo "Demo users: demo_user, demo_anna, demo_liza, demo_admin, demo_moderator\nPassword: $password\n";
} catch (Throwable $e) { $conn->rollback(); throw $e; }
