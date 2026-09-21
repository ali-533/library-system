<?php


define('LIBRARY_APP', true);

session_start();
header('Content-Type: text/html; charset=UTF-8');

const DB_SERVER = '......\\SQLEXPRESS'; // change the dots with your server name copy it when you connect to ssms sql server management studio.
const DB_NAME   = 'LibraryDB';
const LOAN_DAYS = 14;
const TABS      = ['books', 'members', 'borrowings', 'fines'];


/*Escape a value for safe HTML output. */
function h($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** "Borrowed" -> "status-borrowed" */
function statusClass($status): string
{
    return 'status-' . trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string) $status)), '-');
}

function money($value): string
{
    return '$' . number_format((float) $value, 2);
}

function plural(int $n, string $word): string
{
    return $n . ' ' . $word . ($n === 1 ? '' : 's');
}

/** Convert a value returned by SQL Server (string or DateTime) into a DateTime. */
function toDate($value): ?DateTime
{
    if ($value instanceof DateTimeInterface) {
        return new DateTime($value->format('Y-m-d H:i:s'));
    }
    if ($value === null || $value === '') {
        return null;
    }
    try {
        return new DateTime((string) $value);
    } catch (Exception $e) {
        return null;
    }
}

function formatDate($value, string $format = 'Y-m-d'): string
{
    $date = toDate($value);
    return $date ? $date->format($format) : '-';
}

/** Escape LIKE wildcards typed by the user so they are searched literally. */
function likeEscape(string $text): string
{
    return str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $text);
}

/** Turn a PDOException into a short, readable message. */
function friendlyDbError(PDOException $e): string
{
    $native = $e->errorInfo[1] ?? null;

    if ($native == 2627 || $native == 2601) {
        return 'A record with the same unique value (for example ISBN or email) already exists.';
    }
    if ($native == 547) {
        return 'The operation conflicts with related data (foreign key or check constraint).';
    }

    $message = $e->getMessage();
    
    if (preg_match('/\[SQL Server\](.*)$/s', $message, $m)) {
        $message = trim($m[1]);
    }
    return $message;
}

/** Collects (and returns) SQL errors that happened while loading data. */
function queryErrors(?string $add = null): array
{
    static $errors = [];
    if ($add !== null) {
        $errors[] = $add;
    }
    return $errors;
}

/** Run a SELECT and return all rows. Errors are recorded, not hidden. */
function fetchData(?PDO $pdo, string $sql, array $params = []): array
{
    if (!$pdo) {
        return [];
    }
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        queryErrors(friendlyDbError($e));
        return [];
    }
}

/** Run a SELECT and return the first row (or null). Exceptions propagate. */
function fetchRow(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    $stmt->closeCursor();
    return $row === false ? null : $row;
}

/**
 * Execute a stored procedure and read every result set, so that errors raised
 * inside the procedure  are turned into exceptions.
 */
function runProcedure(PDO $pdo, string $sql, array $params): void
{
    $stmt = $pdo->prepare('SET NOCOUNT ON; ' . $sql);
    $stmt->execute($params);
    do {
        if ($stmt->columnCount() > 0) {
            $stmt->fetchAll();
        }
    } while ($stmt->nextRowset());
    $stmt->closeCursor();
}

/* ---- flash messages ---- */
function setFlash(string $message, string $type): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function redirectToTab(string $tab): void
{
    $path = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $path . '?tab=' . urlencode($tab));
    exit;
}

// DATABASE CONNECTION
$pdo             = null;
$connectionError = '';

if (!extension_loaded('pdo_sqlsrv')) {
    $connectionError = 'The PHP extension "pdo_sqlsrv" is not loaded. '
        . 'Enable it in php.ini and restart the web server.';
} else {
    try {
        $dsn = 'sqlsrv:Server=' . DB_SERVER . ';Database=' . DB_NAME . ';TrustServerCertificate=True;';
        // here there is no username or password, because we are using Windows Authentication (Integrated Security)
        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        $connectionError = friendlyDbError($e);
        $pdo = null;
    }
}

//  ACTIONS (each returns a success message or throws an exception error)

function addMember(PDO $pdo, array $in): string
{
    $first   = trim((string) ($in['first_name'] ?? ''));
    $last    = trim((string) ($in['last_name'] ?? ''));
    $email   = trim((string) ($in['email'] ?? ''));
    $phone   = trim((string) ($in['phone'] ?? ''));
    $address = trim((string) ($in['address'] ?? ''));

    if ($first === '' || $last === '') {
        throw new InvalidArgumentException('First name and last name are required.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Please enter a valid email address.');
    }

    $sql = "INSERT INTO Members (first_name, last_name, email, phone, address, membership_status)
            VALUES (?, ?, ?, ?, ?, 'Active')";
    $pdo->prepare($sql)->execute([
        $first,
        $last,
        $email,
        $phone !== '' ? $phone : null,
        $address !== '' ? $address : null,
    ]);

    return 'Member added successfully!';
}

function borrowBook(PDO $pdo, array $in): string
{
    $bookId   = (int) ($in['book_id'] ?? 0);
    $memberId = (int) ($in['member_id'] ?? 0);
    $dueInput = trim((string) ($in['due_date'] ?? ''));
    if ($dueInput === '') {
        $dueInput = date('Y-m-d', strtotime('+' . LOAN_DAYS . ' days'));
    }

    if ($bookId <= 0 || $memberId <= 0) {
        throw new InvalidArgumentException('Please select both a book and a member.');
    }

    $due = DateTime::createFromFormat('!Y-m-d', $dueInput);
    if (!$due || $due->format('Y-m-d') !== $dueInput) {
        throw new InvalidArgumentException('The due date is not valid.');
    }
    if ($due < new DateTime('today')) {
        throw new InvalidArgumentException('The due date cannot be in the past.');
    }

    $book = fetchRow($pdo, 'SELECT available_copies FROM Books WHERE book_id = ?', [$bookId]);
    if (!$book) {
        throw new InvalidArgumentException('The selected book does not exist.');
    }
    if ((int) $book['available_copies'] <= 0) {
        throw new InvalidArgumentException('No copies of this book are available right now.');
    }

    $check = fetchRow($pdo, 'SELECT dbo.fn_CanMemberBorrow(?) AS can_borrow', [$memberId]);
    if (!$check || (int) $check['can_borrow'] !== 1) {
        throw new InvalidArgumentException(
            'Member cannot borrow books. Check membership status or borrowing limit.'
        );
    }

    runProcedure(
        $pdo,
        'EXEC sp_BorrowBook @BookID = ?, @MemberID = ?, @DueDate = ?',
        [$bookId, $memberId, $due->format('Y-m-d')]
    );

    return 'Book borrowed successfully!';
}

function returnBook(PDO $pdo, array $in): string
{
    $borrowId = (int) ($in['borrow_id'] ?? 0);
    if ($borrowId <= 0) {
        throw new InvalidArgumentException('Please select a borrow record.');
    }

    $record = fetchRow($pdo, 'SELECT status FROM BorrowRecords WHERE borrow_id = ?', [$borrowId]);
    if (!$record) {
        throw new InvalidArgumentException('The selected borrow record does not exist.');
    }
    if (!in_array($record['status'], ['Borrowed', 'Overdue'], true)) {
        throw new InvalidArgumentException('This book has already been returned.');
    }

    runProcedure($pdo, 'EXEC sp_ReturnBook @BorrowID = ?', [$borrowId]);

    return 'Book returned successfully!';
}

function addBook(PDO $pdo, array $in): string
{
    $isbn        = trim((string) ($in['isbn'] ?? ''));
    $title       = trim((string) ($in['title'] ?? ''));
    $authorId    = (int) ($in['author_id'] ?? 0);
    $categoryId  = (int) ($in['category_id'] ?? 0);
    $publisherId = (int) ($in['publisher_id'] ?? 0);
    $year        = (int) ($in['publication_year'] ?? 0);
    $priceRaw    = $in['price'] ?? '';
    $copies      = (int) ($in['total_copies'] ?? 1);
    $shelf       = trim((string) ($in['shelf_location'] ?? ''));

    if ($isbn === '' || $title === '' || $shelf === '') {
        throw new InvalidArgumentException('ISBN, title and shelf location are required.');
    }
    if ($authorId <= 0 || $categoryId <= 0 || $publisherId <= 0) {
        throw new InvalidArgumentException('Please select an author, a category and a publisher.');
    }
    if ($year < 1500 || $year > (int) date('Y')) {
        throw new InvalidArgumentException('Publication year must be between 1500 and ' . date('Y') . '.');
    }
    if (!is_numeric($priceRaw) || (float) $priceRaw < 0) {
        throw new InvalidArgumentException('Price must be a number greater than or equal to 0.');
    }
    if ($copies < 1) {
        throw new InvalidArgumentException('Total copies must be at least 1.');
    }

    $sql = "INSERT INTO Books (isbn, title, author_id, category_id, publisher_id,
                               publication_year, price, total_copies, available_copies,
                               shelf_location, book_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Available')";
    $pdo->prepare($sql)->execute([
        $isbn, $title, $authorId, $categoryId, $publisherId,
        $year, (float) $priceRaw, $copies, $copies, $shelf,
    ]);

    return 'Book added successfully!';
}

function payFine(PDO $pdo, array $in): string
{
    $fineId = (int) ($in['fine_id'] ?? 0);
    if ($fineId <= 0) {
        throw new InvalidArgumentException('Please select a fine.');
    }

    $stmt = $pdo->prepare(
        "UPDATE Fines SET status = 'Paid', paid_date = GETDATE()
         WHERE fine_id = ? AND status = 'Unpaid'"
    );
    $stmt->execute([$fineId]);

    if ($stmt->rowCount() === 0) {
        throw new InvalidArgumentException('This fine was not found or has already been paid.');
    }

    return 'Fine paid successfully!';
}
//ajax: view button
if (($_GET['ajax'] ?? '') === 'member') {
    header('Content-Type: application/json; charset=UTF-8');

    if (!$pdo) {
        http_response_code(503);
        echo json_encode(['error' => 'No database connection.']);
        exit;
    }

    $memberId = (int) ($_GET['id'] ?? 0);

    $member = fetchData(
        $pdo,
        'SELECT member_id, first_name, last_name, email, phone, address,
                membership_status, current_borrowed, max_borrow_limit
         FROM Members WHERE member_id = ?',
        [$memberId]
    );

    $loans = fetchData(
        $pdo,
        'SELECT TOP 10 br.borrow_id, b.title, br.borrow_date, br.due_date, br.return_date, br.status
         FROM BorrowRecords br, Books b
         WHERE br.book_id = b.book_id AND br.member_id = ?
         ORDER BY br.borrow_date DESC',
        [$memberId]
    );

    $fines = fetchData(
        $pdo,
        'SELECT fine_id, amount, fine_date, status
         FROM Fines WHERE member_id = ? ORDER BY fine_date DESC',
        [$memberId]
    );

    if (queryErrors()) {
        http_response_code(500);
        echo json_encode(['error' => implode(' ', queryErrors())]);
        exit;
    }
    if (!$member) {
        http_response_code(404);
        echo json_encode(['error' => 'Member not found.']);
        exit;
    }

    foreach ($loans as &$loan) {
        $loan['borrow_date'] = formatDate($loan['borrow_date']);
        $loan['due_date']    = formatDate($loan['due_date']);
        $loan['return_date'] = formatDate($loan['return_date']);
    }
    unset($loan);

    foreach ($fines as &$fine) {
        $fine['amount']    = money($fine['amount']);
        $fine['fine_date'] = formatDate($fine['fine_date']);
    }
    unset($fine);

    echo json_encode(
        ['member' => $member[0], 'loans' => $loans, 'fines' => $fines],
        JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

// HANDLE FORM SUBMISSIONS (POST -> action -> redirect -> GET)
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf'];

$actionTabs = [
    'add_member'  => 'members',
    'borrow_book' => 'borrowings',
    'return_book' => 'borrowings',
    'add_book'    => 'books',
    'pay_fine'    => 'fines',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $tab    = $actionTabs[$action] ?? 'books';

    if (!$pdo) {
        setFlash('There is no database connection.', 'error');
    } elseif (!isset($actionTabs[$action])) {
        setFlash('Unknown action.', 'error');
    } elseif (!hash_equals($csrfToken, (string) ($_POST['csrf'] ?? ''))) {
        setFlash('Your session expired. Please try again.', 'error');
    } else {
        try {
            switch ($action) {
                case 'add_member':  $msg = addMember($pdo, $_POST);  break;
                case 'borrow_book': $msg = borrowBook($pdo, $_POST); break;
                case 'return_book': $msg = returnBook($pdo, $_POST); break;
                case 'add_book':    $msg = addBook($pdo, $_POST);    break;
                case 'pay_fine':    $msg = payFine($pdo, $_POST);    break;
            }
            setFlash($msg, 'success');
        } catch (InvalidArgumentException $e) {
            setFlash($e->getMessage(), 'error');
        } catch (PDOException $e) {
            setFlash('Database error: ' . friendlyDbError($e), 'error');
        }
    }

    redirectToTab($tab); 
}
//Load the page
$flash       = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$message     = $flash['message'] ?? '';
$messageType = ($flash['type'] ?? '') === 'success' ? 'success' : 'error';

$searchTerm     = trim((string) ($_GET['search'] ?? ''));
$categoryFilter = (int) ($_GET['category'] ?? 0);
$requestedTab   = $_GET['tab'] ?? 'books';
$activeTab      = in_array($requestedTab, TABS, true) ? $requestedTab : 'books';
$defaultDueDate = date('Y-m-d', strtotime('+' . LOAN_DAYS . ' days'));
$today          = date('Y-m-d');

/** Dashboard numbers. */
function getLibraryStats(?PDO $pdo): array
{
    $stats = [
        'totalBooks' => 0, 'availableBooks' => 0, 'totalMembers' => 0,
        'activeBorrowings' => 0, 'overdueBooks' => 0, 'totalFines' => 0,
    ];

    $rows = fetchData($pdo, "SELECT
        (SELECT COUNT(*) FROM Books)                                              AS total_books,
        (SELECT COUNT(*) FROM Books WHERE available_copies > 0)                   AS available_books,
        (SELECT COUNT(*) FROM Members WHERE membership_status = 'Active')         AS total_members,
        (SELECT COUNT(*) FROM BorrowRecords WHERE status IN ('Borrowed','Overdue')) AS active_borrowings,
        (SELECT COUNT(*) FROM BorrowRecords
           WHERE status IN ('Borrowed','Overdue')
             AND due_date < CAST(GETDATE() AS DATE))                              AS overdue_books,
        (SELECT ISNULL(SUM(amount), 0) FROM Fines WHERE status = 'Unpaid')        AS total_fines");

    if ($rows) {
        $r = $rows[0];
        $stats['totalBooks']       = (int) $r['total_books'];
        $stats['availableBooks']   = (int) $r['available_books'];
        $stats['totalMembers']     = (int) $r['total_members'];
        $stats['activeBorrowings'] = (int) $r['active_borrowings'];
        $stats['overdueBooks']     = (int) $r['overdue_books'];
        $stats['totalFines']       = (float) $r['total_fines'];
    }
    return $stats;
}

/** Books with author / category / publisher. */
function getBooksWithDetails(?PDO $pdo, string $searchTerm = '', int $categoryFilter = 0): array
{
    $sql = "SELECT b.book_id, b.isbn, b.title, b.author_id, b.category_id, b.publisher_id,
                   b.publication_year, b.price, b.total_copies, b.available_copies,
                   b.shelf_location, b.book_status, b.created_at,
                   a.first_name, a.last_name, c.category_name, p.publisher_name
            FROM Books b, Authors a, Categories c, Publishers p
            WHERE b.author_id = a.author_id
              AND b.category_id = c.category_id
              AND b.publisher_id = p.publisher_id";
    $params = [];

    if ($searchTerm !== '') {
        $sql .= " AND (b.title LIKE ? OR b.isbn LIKE ?
                       OR a.first_name LIKE ? OR a.last_name LIKE ?
                       OR (a.first_name + ' ' + a.last_name) LIKE ?)";
        $like = '%' . likeEscape($searchTerm) . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }
    if ($categoryFilter > 0) {
        $sql .= ' AND b.category_id = ?';
        $params[] = $categoryFilter;
    }

    $sql .= ' ORDER BY b.title';
    return fetchData($pdo, $sql, $params);
}

/** Current (not returned yet) borrowings, with the  overdue computed days. */
function getCurrentBorrowings(?PDO $pdo): array
{
    $rows = fetchData($pdo, "SELECT br.borrow_id, br.book_id, br.member_id, br.borrow_date,
                   br.due_date, br.return_date, br.status, br.fine_amount,
                   b.title, m.first_name, m.last_name
            FROM BorrowRecords br, Books b, Members m
            WHERE br.book_id = b.book_id
              AND br.member_id = m.member_id
              AND br.status IN ('Borrowed', 'Overdue')
            ORDER BY br.due_date");

    $today = new DateTime('today');
    foreach ($rows as &$row) {
        $due = toDate($row['due_date']);
        if ($due) {
            $due->setTime(0, 0, 0);
            $daysLeft = (int) $today->diff($due)->format('%r%a');   // negative = overdue
        } else {
            $daysLeft = 0;
        }
        $row['days_left']  = $daysLeft;
        $row['is_overdue'] = $daysLeft < 0;
    }
    unset($row);

    return $rows;
}

/** Fines with the member's name. */
function getFinesWithDetails(?PDO $pdo): array
{
    return fetchData($pdo, "SELECT f.fine_id, f.borrow_id, f.member_id, f.amount, f.fine_date,
                   f.paid_date, f.status, m.first_name, m.last_name
            FROM Fines f, Members m
            WHERE f.member_id = m.member_id
            ORDER BY f.status, f.fine_date DESC");
}

$stats = getLibraryStats($pdo);

$categories = fetchData($pdo, 'SELECT category_id, category_name FROM Categories ORDER BY category_name');
$authors    = fetchData($pdo, 'SELECT author_id, first_name, last_name FROM Authors ORDER BY last_name, first_name');
$publishers = fetchData($pdo, 'SELECT publisher_id, publisher_name FROM Publishers ORDER BY publisher_name');

$books      = getBooksWithDetails($pdo, $searchTerm, $categoryFilter);
$members    = fetchData($pdo, 'SELECT * FROM Members ORDER BY last_name, first_name');
$borrowings = getCurrentBorrowings($pdo);
$fines      = getFinesWithDetails($pdo);

// Drop-down data for the modals
$availableBooks = fetchData($pdo, 'SELECT b.book_id, b.title, b.available_copies, a.first_name, a.last_name
                                   FROM Books b, Authors a
                                   WHERE b.author_id = a.author_id AND b.available_copies > 0
                                   ORDER BY b.title');
$activeMembers  = fetchData($pdo, "SELECT member_id, first_name, last_name, current_borrowed, max_borrow_limit
                                   FROM Members WHERE membership_status = 'Active'
                                   ORDER BY last_name, first_name");
$returnable     = fetchData($pdo, "SELECT br.borrow_id, b.title, m.first_name, m.last_name
                                   FROM BorrowRecords br, Books b, Members m
                                   WHERE br.book_id = b.book_id AND br.member_id = m.member_id
                                     AND br.status IN ('Borrowed', 'Overdue')
                                   ORDER BY br.borrow_id");

$queryErrors = queryErrors();

// SHOW THE PAGE
require __DIR__ . '/view.php';