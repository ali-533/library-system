<?php
/**
 * view.php - Library Management System (HTML view)
 *
 * This file only prints HTML. All data is prepared in index.php.
 * It has the .php extension because the page contains dynamic data;
 * open the site through index.php, not through this file.
 */
if (!defined('LIBRARY_APP')) {
    http_response_code(403);
    exit('Please open index.php instead.');
}
$cssVersion = (int) @filemtime(__DIR__ . '/style.css');
$jsVersion  = (int) @filemtime(__DIR__ . '/script.js');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management System</title>
    <link rel="stylesheet" href="style.css?v=<?= $cssVersion ?>">
    <script src="script.js?v=<?= $jsVersion ?>" defer></script>
</head>
<body>
<div class="container">

    <!--            Header  -->
    <header class="header">
        <h1>📚 Library Management System</h1>
        <p>Manage your library collection, members, and borrowing activities</p>
        
    </header>

    <!--                Messages  -->
    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= h($messageType) ?>" role="alert"
             <?= $messageType === 'success' ? 'data-autohide' : '' ?>>
            <span><?= h($message) ?></span>
            <button type="button" class="close-alert" data-close-alert aria-label="Close message">×</button>
        </div>
    <?php endif; ?>

    <?php if (!empty($queryErrors)): ?>
        <div class="alert alert-error" role="alert">
            <span>Some data could not be loaded: <?= h(implode(' | ', array_unique($queryErrors))) ?></span>
            <button type="button" class="close-alert" data-close-alert aria-label="Close message">×</button>
        </div>
    <?php endif; ?>

<?php if ($pdo): ?>

    <!--   Dashboard  -->
    <section class="dashboard">
        <div class="card">
            <h3>📊 Library Overview</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?= (int) $stats['totalBooks'] ?></div>
                    <div class="stat-label">Total Books</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= (int) $stats['availableBooks'] ?></div>
                    <div class="stat-label">Available</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= (int) $stats['totalMembers'] ?></div>
                    <div class="stat-label">Active Members</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= (int) $stats['activeBorrowings'] ?></div>
                    <div class="stat-label">Borrowed Books</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= (int) $stats['overdueBooks'] ?></div>
                    <div class="stat-label">Overdue</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= h(money($stats['totalFines'])) ?></div>
                    <div class="stat-label">Unpaid Fines</div>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>🚀 Quick Actions</h3>
            <div class="action-buttons">
                <button type="button" class="btn btn-primary" data-open-modal="addBookModal">➕ Add New Book</button>
                <button type="button" class="btn btn-success" data-open-modal="addMemberModal">👤 Add Member</button>
                <button type="button" class="btn btn-warning" data-open-modal="borrowBookModal">📖 Borrow Book</button>
                <button type="button" class="btn btn-danger" data-open-modal="returnBookModal">↩️ Return Book</button>
            </div>
        </div>
    </section>

    <!-- Tabs  -->
    <nav class="tabs" role="tablist">
        <?php
        $tabLabels = [
            'books'      => '📚 Books',
            'members'    => '👥 Members',
            'borrowings' => '🔄 Borrowings',
            'fines'      => '💰 Fines',
        ];
        foreach ($tabLabels as $key => $label): ?>
            <button type="button" role="tab" class="tab <?= $activeTab === $key ? 'active' : '' ?>"
                    data-tab="<?= h($key) ?>" aria-selected="<?= $activeTab === $key ? 'true' : 'false' ?>">
                <?= h($label) ?>
            </button>
        <?php endforeach; ?>
    </nav>

    <!-- Books tab  -->
    <section id="booksTab" class="tab-content <?= $activeTab === 'books' ? 'active' : '' ?>">
        <form class="search-bar" method="get" action="">
            <input type="hidden" name="tab" value="books">
            <input type="text" name="search" id="searchInput" value="<?= h($searchTerm) ?>"
                   placeholder="Search books by title, ISBN, or author..." aria-label="Search books">
            <select name="category" id="filterSelect" aria-label="Filter by category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['category_id'] ?>"
                        <?= $categoryFilter === (int) $category['category_id'] ? 'selected' : '' ?>>
                        <?= h($category['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">🔍 Search</button>
            <?php if ($searchTerm !== '' || $categoryFilter > 0): ?>
                <a class="btn btn-clear" href="?tab=books">Clear</a>
            <?php endif; ?>
        </form>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>ISBN</th>
                        <th>Available</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($books) > 0): ?>
                    <?php foreach ($books as $book): ?>
                        <tr>
                            <td><?= (int) $book['book_id'] ?></td>
                            <td><strong><?= h($book['title']) ?></strong></td>
                            <td><?= h($book['first_name'] . ' ' . $book['last_name']) ?></td>
                            <td><?= h($book['category_name']) ?></td>
                            <td><?= h($book['isbn']) ?></td>
                            <td><?= (int) $book['available_copies'] ?>/<?= (int) $book['total_copies'] ?></td>
                            <td><span class="<?= h(statusClass($book['book_status'])) ?>"><?= h($book['book_status']) ?></span></td>
                            <td>
                                <?php if ((int) $book['available_copies'] > 0): ?>
                                    <button type="button" class="btn btn-primary btn-small"
                                            data-quick-borrow="<?= (int) $book['book_id'] ?>">Borrow</button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-primary btn-small" disabled>Unavailable</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="empty-row">
                            No books found. <?= ($searchTerm !== '' || $categoryFilter > 0) ? 'Try a different search.' : 'Add some books to get started.' ?>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!--  Members tab  -->
    <section id="membersTab" class="tab-content <?= $activeTab === 'members' ? 'active' : '' ?>">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Borrowed</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($members) > 0): ?>
                    <?php foreach ($members as $member): ?>
                        <tr>
                            <td><?= (int) $member['member_id'] ?></td>
                            <td><strong><?= h($member['first_name'] . ' ' . $member['last_name']) ?></strong></td>
                            <td><?= h($member['email']) ?></td>
                            <td><?= h($member['phone'] ?? 'N/A') ?></td>
                            <td><span class="<?= h(statusClass($member['membership_status'])) ?>"><?= h($member['membership_status']) ?></span></td>
                            <td><?= (int) ($member['current_borrowed'] ?? 0) ?>/<?= (int) ($member['max_borrow_limit'] ?? 0) ?></td>
                            <td>
                                <button type="button" class="btn btn-primary btn-small"
                                        data-member="<?= (int) $member['member_id'] ?>">View</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty-row">No members found. Add some members to get started.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!--  Borrowings tab  -->
    <section id="borrowingsTab" class="tab-content <?= $activeTab === 'borrowings' ? 'active' : '' ?>">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Borrow ID</th>
                        <th>Book</th>
                        <th>Member</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Days Left</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($borrowings) > 0): ?>
                    <?php foreach ($borrowings as $borrow): ?>
                        <tr>
                            <td><?= (int) $borrow['borrow_id'] ?></td>
                            <td><?= h($borrow['title']) ?></td>
                            <td><?= h($borrow['first_name'] . ' ' . $borrow['last_name']) ?></td>
                            <td><?= h(formatDate($borrow['borrow_date'])) ?></td>
                            <td><?= h(formatDate($borrow['due_date'])) ?></td>
                            <td>
                                <?php if ($borrow['is_overdue']): ?>
                                    <span class="status-overdue">Overdue: <?= h(plural(abs($borrow['days_left']), 'day')) ?></span>
                                <?php elseif ($borrow['days_left'] === 0): ?>
                                    <span class="status-borrowed">Due today</span>
                                <?php else: ?>
                                    <span class="status-available"><?= h(plural($borrow['days_left'], 'day')) ?> left</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-success btn-small"
                                        data-return="<?= (int) $borrow['borrow_id'] ?>">Return</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty-row">No active borrowings found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!--  Fines tab  -->
    <section id="finesTab" class="tab-content <?= $activeTab === 'fines' ? 'active' : '' ?>">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Fine ID</th>
                        <th>Member</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($fines) > 0): ?>
                    <?php foreach ($fines as $fine): ?>
                        <tr>
                            <td><?= (int) $fine['fine_id'] ?></td>
                            <td><?= h($fine['first_name'] . ' ' . $fine['last_name']) ?></td>
                            <td><?= h(money($fine['amount'])) ?></td>
                            <td><?= h(formatDate($fine['fine_date'])) ?></td>
                            <td><span class="<?= h(statusClass($fine['status'])) ?>"><?= h($fine['status']) ?></span></td>
                            <td>
                                <?php if ($fine['status'] === 'Unpaid'): ?>
                                    <button type="button" class="btn btn-success btn-small"
                                            data-pay-fine="<?= (int) $fine['fine_id'] ?>"
                                            data-amount="<?= h(money($fine['amount'])) ?>">Pay</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="empty-row">No fines found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php else: ?>

    <!--  Shown when the database is unreachable  -->
    <section class="card connection-help">
        <h3>⚠️ Database Connection Required</h3>
        <p>Please make sure SQL Server is running and the connection details are correct.</p>
        <p><strong>Error:</strong> <?= h($connectionError) ?></p>
        <div class="connection-details">
            <h4>Connection Details</h4>
            <p>Server: <?= h(DB_SERVER) ?></p>
            <p>Database: <?= h(DB_NAME) ?></p>
            <p>Using: PDO SQL Server driver (Windows Authentication)</p>
        </div>
    </section>

<?php endif; ?>

    <footer class="footer">
        <p>Library Management System &copy; <?= date('Y') ?> | Built with PHP PDO &amp; SQL Server</p>
    </footer>
</div>

<?php if ($pdo): ?>
<!-- Modals  -->

<!-- Add Book -->
<div id="addBookModal" class="form-modal" role="dialog" aria-modal="true" aria-labelledby="addBookTitle">
    <div class="form-content">
        <h3 id="addBookTitle">➕ Add New Book</h3>
        <form method="post" action="">
            <input type="hidden" name="action" value="add_book">
            <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">

            <div class="form-group">
                <label for="bookIsbn">ISBN:</label>
                <input type="text" id="bookIsbn" name="isbn" required placeholder="e.g., 9780451524935">
            </div>

            <div class="form-group">
                <label for="bookTitle">Title:</label>
                <input type="text" id="bookTitle" name="title" required placeholder="Book title">
            </div>

            <div class="form-group">
                <label for="bookAuthor">Author:</label>
                <select id="bookAuthor" name="author_id" required>
                    <option value="">Select Author</option>
                    <?php foreach ($authors as $author): ?>
                        <option value="<?= (int) $author['author_id'] ?>">
                            <?= h($author['first_name'] . ' ' . $author['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="bookCategory">Category:</label>
                <select id="bookCategory" name="category_id" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['category_id'] ?>"><?= h($category['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="bookPublisher">Publisher:</label>
                <select id="bookPublisher" name="publisher_id" required>
                    <option value="">Select Publisher</option>
                    <?php foreach ($publishers as $publisher): ?>
                        <option value="<?= (int) $publisher['publisher_id'] ?>"><?= h($publisher['publisher_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="bookYear">Publication Year:</label>
                <input type="number" id="bookYear" name="publication_year" min="1500" max="<?= date('Y') ?>" required>
            </div>

            <div class="form-group">
                <label for="bookPrice">Price ($):</label>
                <input type="number" id="bookPrice" name="price" step="0.01" min="0" required>
            </div>

            <div class="form-group">
                <label for="bookCopies">Total Copies:</label>
                <input type="number" id="bookCopies" name="total_copies" min="1" value="1" required>
            </div>

            <div class="form-group">
                <label for="bookShelf">Shelf Location:</label>
                <input type="text" id="bookShelf" name="shelf_location" required placeholder="e.g., FIC-A1">
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-cancel" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Add Book</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Member -->
<div id="addMemberModal" class="form-modal" role="dialog" aria-modal="true" aria-labelledby="addMemberTitle">
    <div class="form-content">
        <h3 id="addMemberTitle">👤 Add New Member</h3>
        <form method="post" action="">
            <input type="hidden" name="action" value="add_member">
            <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">

            <div class="form-group">
                <label for="memberFirst">First Name:</label>
                <input type="text" id="memberFirst" name="first_name" required>
            </div>

            <div class="form-group">
                <label for="memberLast">Last Name:</label>
                <input type="text" id="memberLast" name="last_name" required>
            </div>

            <div class="form-group">
                <label for="memberEmail">Email:</label>
                <input type="email" id="memberEmail" name="email" required>
            </div>

            <div class="form-group">
                <label for="memberPhone">Phone:</label>
                <input type="text" id="memberPhone" name="phone">
            </div>

            <div class="form-group">
                <label for="memberAddress">Address:</label>
                <textarea id="memberAddress" name="address" rows="3"></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-cancel" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-success">Add Member</button>
            </div>
        </form>
    </div>
</div>

<!-- Borrow Book -->
<div id="borrowBookModal" class="form-modal" role="dialog" aria-modal="true" aria-labelledby="borrowTitle">
    <div class="form-content">
        <h3 id="borrowTitle">📖 Borrow Book</h3>
        <form method="post" action="">
            <input type="hidden" name="action" value="borrow_book">
            <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">

            <div class="form-group">
                <label for="borrowBook">Select Book:</label>
                <select id="borrowBook" name="book_id" required>
                    <option value="">Select Book</option>
                    <?php foreach ($availableBooks as $book): ?>
                        <option value="<?= (int) $book['book_id'] ?>">
                            <?= h($book['title']) ?> by <?= h($book['first_name'] . ' ' . $book['last_name']) ?>
                            (Available: <?= (int) $book['available_copies'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="borrowMember">Select Member:</label>
                <select id="borrowMember" name="member_id" required>
                    <option value="">Select Member</option>
                    <?php foreach ($activeMembers as $member): ?>
                        <option value="<?= (int) $member['member_id'] ?>">
                            <?= h($member['first_name'] . ' ' . $member['last_name']) ?>
                            (Borrowed: <?= (int) ($member['current_borrowed'] ?? 0) ?>/<?= (int) ($member['max_borrow_limit'] ?? 0) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="borrowDue">Due Date:</label>
                <input type="date" id="borrowDue" name="due_date" value="<?= h($defaultDueDate) ?>"
                       min="<?= h($today) ?>" required>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-cancel" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-warning">Borrow Book</button>
            </div>
        </form>
    </div>
</div>

<!-- Return Book -->
<div id="returnBookModal" class="form-modal" role="dialog" aria-modal="true" aria-labelledby="returnTitle">
    <div class="form-content">
        <h3 id="returnTitle">↩️ Return Book</h3>
        <form method="post" action="">
            <input type="hidden" name="action" value="return_book">
            <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">

            <div class="form-group">
                <label for="returnBorrow">Select Borrow Record:</label>
                <select id="returnBorrow" name="borrow_id" required>
                    <option value="">Select Borrow Record</option>
                    <?php foreach ($returnable as $borrow): ?>
                        <option value="<?= (int) $borrow['borrow_id'] ?>">
                            ID <?= (int) $borrow['borrow_id'] ?>: <?= h($borrow['title']) ?>
                            (<?= h($borrow['first_name'] . ' ' . $borrow['last_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-cancel" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-danger">Return Book</button>
            </div>
        </form>
    </div>
</div>

<!-- Pay Fine -->
<div id="payFineModal" class="form-modal" role="dialog" aria-modal="true" aria-labelledby="payFineTitle">
    <div class="form-content">
        <h3 id="payFineTitle">💰 Pay Fine</h3>
        <form method="post" action="">
            <input type="hidden" name="action" value="pay_fine">
            <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
            <input type="hidden" id="fineIdInput" name="fine_id">

            <div class="form-group">
                <label for="fineAmountInput">Fine Amount:</label>
                <input type="text" id="fineAmountInput" readonly>
            </div>

            <div class="form-group">
                <label for="paymentMethod">Payment Method:</label>
                <select id="paymentMethod" name="payment_method" required>
                    <option value="Cash">Cash</option>
                    <option value="Credit Card">Credit Card</option>
                    <option value="Debit Card">Debit Card</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-cancel" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-success">Pay Fine</button>
            </div>
        </form>
    </div>
</div>

<!-- Member details (filled by script.js) -->
<div id="memberModal" class="form-modal" role="dialog" aria-modal="true" aria-labelledby="memberModalTitle">
    <div class="form-content form-content-wide">
        <h3 id="memberModalTitle">👤 Member Details</h3>
        <div id="memberDetails" class="member-details" aria-live="polite"></div>
        <div class="form-actions">
            <button type="button" class="btn btn-cancel" data-close-modal>Close</button>
        </div>
    </div>
</div>
<?php endif; ?>

</body>
</html>