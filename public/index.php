<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require '../src/vendor/autoload.php';
$app = new \Slim\App;
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "library";

// Function to generate a new access token
function generateAccessToken() {
    $key = 'server_hack';
    $iat = time();
    $accessExp = $iat + 3600; // 1 hour expiration
    $payload = [
        'iss' => 'http://library.org',
        'aud' => 'http://library.com',
        'iat' => $iat,
        'exp' => $accessExp,
    ];
    return JWT::encode($payload, $key, 'HS256');
}

// Register a new user
$app->post('/user/register', function (Request $request, Response $response) use ($servername, $username, $password, $dbname) {
    $data = json_decode($request->getBody());
    $uname = $data->username;
    $pass = $data->password;
    
    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $hashedPassword = password_hash($pass, PASSWORD_BCRYPT);
        $sql = "INSERT INTO users (username, password) VALUES (:uname, :pass)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':uname' => $uname, ':pass' => $hashedPassword]);
        $response->getBody()->write(json_encode(["status" => "success", "access_token" => null, "data" => null]));

    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(["status" => "fail", "access_token" => null, "data" => ["title" => $e->getMessage()]]));
    }

    $conn = null;
    return $response;
});

// Authenticate a user and generate access token
$app->post('/user/auth', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname) {
    error_reporting(E_ALL);
    $data = json_decode($request->getBody());
    $uname = $data->username;
    $pass = $data->password;

    try {    
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT * FROM users WHERE username = :uname";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':uname' => $uname]);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $userData = $stmt->fetch();

        if ($userData && password_verify($pass, $userData['password'])) {
            // Generate Access Token
            $accessToken = generateAccessToken();
            // Store the token in the database without user reference
            storeToken($accessToken);

            // Return the new access token
            $response->getBody()->write(json_encode([
                "status" => "success",
                "access_token" => $accessToken,
                "data" => null
            ]));
        } else {
            $response->getBody()->write(json_encode(["status" => "fail", "access_token" => null, "data" => ["title" => "Authentication Failed"]]));
        }

    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(["status" => "fail", "access_token" => null, "data" => ["title" => $e->getMessage()]]));
    }

    $conn = null;
    return $response;
});

// Function to store tokens in the database
function storeToken($token) {
    $conn = new PDO("mysql:host=localhost;dbname=library", "root", "");
    $sql = "INSERT INTO jwt_tokens (token, used, created_at) VALUES (:token, 0, NOW())"; // Added created_at column
    $stmt = $conn->prepare($sql);
    $stmt->execute([':token' => $token]);
}

// Function to delete expired tokens
function deleteExpiredTokens() {
    $conn = new PDO("mysql:host=localhost;dbname=library", "root", "");
    $sql = "DELETE FROM jwt_tokens WHERE created_at < NOW() - INTERVAL 1 DAY"; // Deletes tokens older than 1 day
    $stmt = $conn->prepare($sql);
    $stmt->execute();
}

// Validate token function
function validateToken($request, $response, $next) {
    // Call the function to delete expired tokens
    deleteExpiredTokens();

    $data = json_decode($request->getBody(), true);

    if (!isset($data['token'])) {
        return $response->withStatus(401)->write(json_encode(["status" => "fail", "access_token" => null, "message" => "Token missing"]));
    }

    $token = $data['token'];
    $key = 'server_hack';

    try {
        $decoded = JWT::decode($token, new Key($key, 'HS256'));

        // Check if the access token has expired
        if ($decoded->exp < time()) {
            return $response->withStatus(401)->write(json_encode(["status" => "fail", "access_token" => null, "message" => "Token expired"]));
        }

        // Check if the token has already been used
        $conn = new PDO("mysql:host=localhost;dbname=library", "root", "");
        $sql = "SELECT used FROM jwt_tokens WHERE token = :token";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':token' => $token]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tokenData || $tokenData['used'] == 1) {
            return $response->withStatus(401)->write(json_encode(["status" => "fail", "access_token" => null, "message" => "Token already used or invalid"]));
        }

    } catch (Exception $e) {
        return $response->withStatus(401)->write(json_encode(["status" => "fail", "access_token" => null, "message" => "Unauthorized"]));
    }

    return $next($request, $response);
}

// Function to mark the token as used
function markTokenAsUsed($token) {
    $conn = new PDO("mysql:host=localhost;dbname=library", "root", "");
    $sql = "UPDATE jwt_tokens SET used    = 1 WHERE token = :token";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':token' => $token]);
}

// Function to respond with new access token included
function respondWithNewAccessToken(Response $response) {
    $newAccessToken = generateAccessToken();
    storeToken($newAccessToken); // Store the new token
    return $response->withHeader('New-Access-Token', $newAccessToken);
}

// Create Author
$app->post('/authors', function (Request $request, Response $response) {
    $data = json_decode($request->getBody(), true);
    $name = $data['name'];

    $conn = new PDO("mysql:host=localhost;dbname=library", "root", "");
    $sql = "INSERT INTO authors (name) VALUES (:name)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':name' => $name]);

    // Mark the old token as used
    markTokenAsUsed($data['token']); // Pass the token from the request

    // Respond with a new access token
    $response = respondWithNewAccessToken($response);
    $response->getBody()->write(json_encode(["status" => "success", "access_token" => $response->getHeader('New-Access-Token')[0], "data" => null]));
    return $response;
})->add('validateToken');

// Get All Authors
$app->get('/authors/get', function (Request $request, Response $response) {
    $conn = new PDO("mysql:host=localhost;dbname=library", "root", "");
    $stmt = $conn->query("SELECT * FROM authors");
    $authors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark the old token as used
    markTokenAsUsed($request->getParsedBody()['token']); // Pass the token from the request

    // Respond with a new access token
    $response = respondWithNewAccessToken($response);
    $response->getBody()->write(json_encode(["status" => "success", "access_token" => $response->getHeader('New-Access-Token')[0], "data" => $authors]));
    return $response;
})->add('validateToken');

// Update Author
$app->put('/authors/update/{id}', function (Request $request, Response $response, array $args) {
    $data = json_decode($request->getBody(), true);
    $id = $args['id'];
    $name = $data['name'];

    $conn = new PDO("mysql:host=localhost;dbname=library", "root", "");
    $sql = "UPDATE authors SET name = :name WHERE authorid = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':name' => $name, ':id' => $id]);

    // Mark the old token as used
    markTokenAsUsed($data['token']); // Pass the token from the request

    // Respond with a new access token
    $response = respondWithNewAccessToken($response);
    $response->getBody()->write(json_encode(["status" => "success", "access_token" => $response->getHeader('New-Access-Token')[0], "data" => null]));
    return $response;
})->add('validateToken');

// Delete Author
$app->delete('/authors/delete/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];

    $conn = new PDO("mysql:host=localhost;dbname=library", "root", "");
    $sql = "DELETE FROM authors WHERE authorid = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);

    // Mark the old token as used
    markTokenAsUsed($request->getParsedBody()['token']); // Pass the token from the request

    // Respond with a new access token
    $response = respondWithNewAccessToken($response);
    $response->getBody()->write(json_encode(["status" => "success", "access_token" => $response->getHeader('New-Access-Token')[0], "data" => null]));
    return $response;
})->add('validateToken');

// Create Book
$app->post('/books', function (Request $request, Response $response) {
    $data = json_decode($request->getBody(), true);
    $title = $data['title'];
    $author_id = $data['author_id'];

    $conn = new PDO("mysql:host=localhost;dbname=library", "root", "");
    $sql = "INSERT INTO books (title, authorid) VALUES (:title, :authorid)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':title' => $title, ':authorid' => $author_id]);

    // Mark the old token as used
    markTokenAsUsed($data['token']); // Pass the token from the request

    // Respond with a new access token
    $response = respondWithNewAccessToken($response);
    $response->getBody()->write(json_encode(["status" => "success", "access_token" => $response->getHeader('New-Access-Token')[0], "data" => null]));
    return $response;
})->add('validateToken');

// Get All Books
$app->get('/books/get', function (Request $request, Response $response) {
    $conn = new PDO("mysql:host=localhost;dbname=library", "root", "");
    $stmt = $conn->query("SELECT * FROM books");
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark the old token as used
    markTokenAsUsed($request->getParsedBody()['token']); // Pass the token from the request

    // Respond with a new access token
    $response = respondWithNewAccessToken($response);
    $response->getBody()->write(json_encode(["status" => "success", "access_token" => $response->getHeader('New-Access-Token')[0], "data" => $books]));
    return $response;
})->add('validateToken');

// Update Book
$app->put('/books/update/{id}', function (Request $request, Response $response, array $args) {
    $data = json_decode($request->getBody(), true);
    $id = $args['id'];
    $title = $data['title'];
    $author_id = $data['author_id'];

    $conn = new PDO("mysql:host=localhost;dbname=library", "root", "");
    $sql = "UPDATE books SET title = :title, authorid = :authorid WHERE bookid = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':title' => $title, ':authorid' => $author_id, ':id' => $id]);

    // Mark the old token as used
    markTokenAsUsed($data['token']); // Pass the token from the request

    // Respond with a new access token
    $response = respondWithNewAccessToken($response);
    $response->getBody()->write(json_encode(["status" => "success", "access_token" => $response->getHeader('New-Access-Token')[0], "data" => null]));
    return $response;
})->add('validateToken');

// Delete Book
$app->delete('/books/delete/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];

    $conn = new PDO("mysql:host=localhost;dbname=library", "root", "");
    $sql = "DELETE FROM books WHERE bookid = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);

    // Mark the old token as used
    markTokenAsUsed($request->getParsedBody()['token']); // Pass the token from the request

    // Respond with a new access token
    $response = respondWithNewAccessToken($response);
    $response->getBody()->write(json_encode(["status" => "success", "access_token" => $response->getHeader('New-Access-Token')[0], "data" => null]));
    return $response;
})->add('validateToken');

// Get Books by Author ID
$app->post('/books/get_by_author', function (Request $request, Response $response) {
    $data = json_decode($request->getBody(), true);
    $author_id = $data['author_id'];

    // Validate that author_id is provided
    if (empty($author_id)) {
        return $response->withStatus(400)->write(json_encode(["status" => "fail", "access_token" => null, "message" => "author_id is required"]));
    }

    try {
        $conn = new PDO("mysql:host=localhost;dbname=library", "root", "");
        $sql = "SELECT * FROM books WHERE authorid = :authorid";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':authorid' => $author_id]);
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark the old token as used
        markTokenAsUsed($request->getParsedBody()['token']); // Pass the token from the request

        // Respond with a new access token
        $response = respondWithNewAccessToken($response);
        $response->getBody()->write(json_encode(["status" => "success", "access_token" => $response->getHeader('New-Access-Token')[0], "data" => $books]));
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(["status" => "fail", "access_token" => null, "data" => ["title" => $e->getMessage()]]));
    }

    return $response;
})->add('validateToken');

// Create Book-Author Relations
$app->post('/books_authors', function (Request $request, Response $response) {
    $data = json_decode($request->getBody(), true);
    $book_id = $data['book_id'];
    $author_id = $data['author_id'];

    $conn = new PDO("mysql:host=localhost;dbname=library", "root", "");
    $sql = "INSERT INTO books_authors (bookid, authorid) VALUES (:bookid, :authorid)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':bookid' => $book_id, ':authorid' => $author_id]);

    // Mark the old token as used
    markTokenAsUsed($data['token']); // Pass the token from the request

    // Respond with a new access token
    $response = respondWithNewAccessToken($response);
    $response->getBody()->write(json_encode(["status" => "success", "access_token" => $response->getHeader('New-Access-Token')[0], "data" => null]));
    return $response;
})->add('validateToken');

// Get All Book-Author Relations
$app->get('/books_authors/get', function (Request $request, Response $response) {
    $conn = new PDO("mysql:host=localhost;dbname=library", "root", "");
    $stmt = $conn->query("SELECT * FROM books_authors");
    $relations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark the old token as used
    markTokenAsUsed($request->getParsedBody()['token']); // Pass the token from the request

    // Respond with a new access token
    $response = respondWithNewAccessToken($response);
    $response->getBody()->write(json_encode(["status" => "success", "access_token" => $response->getHeader('New-Access-Token')[0], "data" => $relations]));
    return $response;
})->add('validateToken');

// Delete Book-Author Relations
$app->delete('/books_authors/delete/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];

    $conn = new PDO("mysql:host=localhost;dbname=library", "root", "");
    $sql = "DELETE FROM books_authors WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);

    // Mark the old token as used
    markTokenAsUsed($request->getParsedBody()['token']); // Pass the token from the request

    // Respond with a new access token
    $response = respondWithNewAccessToken($response);
    $response->getBody()->write(json_encode(["status" => "success", "access_token" => $response->getHeader('New-Access-Token')[0], "data" => null]));
    return $response;
})->add('validateToken');

$app->run();
