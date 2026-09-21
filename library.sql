--alter database LibraryDB SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
--drop database LibraryDB;



-- LIBRARY DATABASE SYSTEM with php front end development

-- 1. DATABASE CREATION
CREATE DATABASE LibraryDB;
GO

USE LibraryDB;
GO

PRINT 'Database LibraryDB created successfully at ' + CONVERT(VARCHAR, GETDATE(), 120);
GO

-- 2. TABLE CREATION

-- 2.1 CATEGORIES TABLE
CREATE TABLE Categories (
    category_id INT IDENTITY(1,1) PRIMARY KEY,
    category_name NVARCHAR(100) NOT NULL,
    description NVARCHAR(500) NULL,
    created_at DATETIME DEFAULT GETDATE()
);
GO

-- 2.2 AUTHORS TABLE
CREATE TABLE Authors (
    author_id INT IDENTITY(1,1) PRIMARY KEY,
    first_name NVARCHAR(50) NOT NULL,
    last_name NVARCHAR(50) NOT NULL,
    email NVARCHAR(100) NULL,
    biography NVARCHAR(MAX) NULL,
    created_at DATETIME DEFAULT GETDATE()
);
GO

-- 2.3 PUBLISHERS TABLE
CREATE TABLE Publishers (
    publisher_id INT IDENTITY(1,1) PRIMARY KEY,
    publisher_name NVARCHAR(100) NOT NULL,
    address NVARCHAR(200) NULL,
    phone NVARCHAR(20) NULL,
    email NVARCHAR(100) NULL,
    created_at DATETIME DEFAULT GETDATE()
);
GO

-- 2.4 BOOKS TABLE
CREATE TABLE Books (
    book_id INT IDENTITY(1,1) PRIMARY KEY,
    isbn NVARCHAR(20) NOT NULL UNIQUE,
    title NVARCHAR(200) NOT NULL,
    author_id INT NOT NULL,
    category_id INT NOT NULL,
    publisher_id INT NULL,
    publication_year INT NULL,
    price DECIMAL(10,2) NULL,
    total_copies INT NOT NULL DEFAULT 1,
    available_copies INT NOT NULL DEFAULT 1,
    shelf_location NVARCHAR(50) NULL,
    book_status NVARCHAR(20) DEFAULT 'Available',
    created_at DATETIME DEFAULT GETDATE(),
    
    CONSTRAINT FK_Books_Authors FOREIGN KEY (author_id) 
        REFERENCES Authors(author_id) ON DELETE NO ACTION,
    
    CONSTRAINT FK_Books_Categories FOREIGN KEY (category_id) 
        REFERENCES Categories(category_id) ON DELETE NO ACTION,
    
    CONSTRAINT FK_Books_Publishers FOREIGN KEY (publisher_id) 
        REFERENCES Publishers(publisher_id) ON DELETE SET NULL,
    
    CONSTRAINT CHK_Books_TotalCopies CHECK (total_copies >= 0),
    CONSTRAINT CHK_Books_AvailableCopies CHECK (available_copies >= 0 AND available_copies <= total_copies),
    CONSTRAINT CHK_Books_PublicationYear CHECK (publication_year >= 1500 AND publication_year <= YEAR(GETDATE())),
    CONSTRAINT CHK_Books_Status CHECK (book_status IN ('Available', 'Borrowed', 'Reserved', 'Maintenance', 'Lost'))
);
GO

-- 2.5 MEMBERS TABLE
CREATE TABLE Members (
    member_id INT IDENTITY(1,1) PRIMARY KEY,
    first_name NVARCHAR(50) NOT NULL,
    last_name NVARCHAR(50) NOT NULL,
    email NVARCHAR(100) NOT NULL UNIQUE,
    phone NVARCHAR(20) NULL,
    address NVARCHAR(200) NULL,
    membership_date DATE DEFAULT GETDATE(),
    membership_expiry DATE NULL,
    membership_status NVARCHAR(20) DEFAULT 'Active',
    max_borrow_limit INT DEFAULT 5,
    current_borrowed INT DEFAULT 0,
    created_at DATETIME DEFAULT GETDATE(),
    
    CONSTRAINT CHK_Members_Email CHECK (email LIKE '%_@__%.__%'),
    CONSTRAINT CHK_Members_Status CHECK (membership_status IN ('Active', 'Suspended', 'Expired', 'Inactive')),
    CONSTRAINT CHK_Members_BorrowLimit CHECK (current_borrowed >= 0 AND current_borrowed <= max_borrow_limit)
);
GO

-- 2.6 USERS TABLE (Library Staff)
CREATE TABLE Users (
    user_id INT IDENTITY(1,1) PRIMARY KEY,
    username NVARCHAR(50) NOT NULL UNIQUE,
    password_hash NVARCHAR(255) NOT NULL,
    email NVARCHAR(100) NOT NULL UNIQUE,
    first_name NVARCHAR(50) NOT NULL,
    last_name NVARCHAR(50) NOT NULL,
    role NVARCHAR(30) NOT NULL,
    is_active BIT DEFAULT 1,
    last_login DATETIME NULL,
    created_at DATETIME DEFAULT GETDATE(),
    
    CONSTRAINT CHK_Users_Role CHECK (role IN ('Admin', 'Librarian', 'Staff'))
);
GO

-- 2.7 BORROW_RECORDS TABLE
CREATE TABLE BorrowRecords (
    borrow_id INT IDENTITY(1,1) PRIMARY KEY,
    book_id INT NOT NULL,
    member_id INT NOT NULL,
    borrow_date DATETIME DEFAULT GETDATE(),
    due_date DATE NOT NULL,
    return_date DATETIME NULL,
    status NVARCHAR(20) DEFAULT 'Borrowed',
    fine_amount DECIMAL(10,2) DEFAULT 0.00,
    notes NVARCHAR(500) NULL,
    
    CONSTRAINT FK_BorrowRecords_Books FOREIGN KEY (book_id) 
        REFERENCES Books(book_id) ON DELETE NO ACTION,
    
    CONSTRAINT FK_BorrowRecords_Members FOREIGN KEY (member_id) 
        REFERENCES Members(member_id) ON DELETE NO ACTION,
    
    CONSTRAINT CHK_BorrowRecords_Status CHECK (status IN ('Borrowed', 'Returned', 'Overdue', 'Lost')),
    CONSTRAINT CHK_BorrowRecords_Dates CHECK (due_date >= CAST(borrow_date AS DATE)),
    CONSTRAINT CHK_BorrowRecords_ReturnDate CHECK (return_date IS NULL OR return_date >= borrow_date),
    CONSTRAINT CHK_BorrowRecords_FineAmount CHECK (fine_amount >= 0)
);
GO

-- 2.8 RESERVATIONS TABLE
CREATE TABLE Reservations (
    reservation_id INT IDENTITY(1,1) PRIMARY KEY,
    book_id INT NOT NULL,
    member_id INT NOT NULL,
    reservation_date DATETIME DEFAULT GETDATE(),
    status NVARCHAR(20) DEFAULT 'Pending',
    expiry_date DATETIME NULL,
    notes NVARCHAR(500) NULL,
    
    CONSTRAINT FK_Reservations_Books FOREIGN KEY (book_id) 
        REFERENCES Books(book_id) ON DELETE NO ACTION,
    
    CONSTRAINT FK_Reservations_Members FOREIGN KEY (member_id) 
        REFERENCES Members(member_id) ON DELETE NO ACTION,
    
    CONSTRAINT CHK_Reservations_Status CHECK (status IN ('Pending', 'Confirmed', 'Cancelled', 'Expired', 'Fulfilled'))
);
GO

-- 2.9 FINES TABLE
CREATE TABLE Fines (
    fine_id INT IDENTITY(1,1) PRIMARY KEY,
    borrow_id INT NOT NULL,
    member_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    fine_date DATE DEFAULT GETDATE(),
    paid_date DATE NULL,
    payment_method NVARCHAR(30) NULL,
    status NVARCHAR(20) DEFAULT 'Unpaid',
    notes NVARCHAR(500) NULL,
    
    CONSTRAINT FK_Fines_BorrowRecords FOREIGN KEY (borrow_id) 
        REFERENCES BorrowRecords(borrow_id) ON DELETE NO ACTION,
    
    CONSTRAINT FK_Fines_Members FOREIGN KEY (member_id) 
        REFERENCES Members(member_id) ON DELETE NO ACTION,
    
    CONSTRAINT CHK_Fines_Status CHECK (status IN ('Unpaid', 'Paid', 'Waived', 'Partially Paid')),
    CONSTRAINT CHK_Fines_Amount CHECK (amount >= 0),  -- Changed from > 0 to >= 0
    CONSTRAINT CHK_Fines_PaidDate CHECK (paid_date IS NULL OR paid_date >= fine_date)
);
GO

-- 2.10 AUDIT LOG TABLE
CREATE TABLE AuditLog (
    log_id INT IDENTITY(1,1) PRIMARY KEY,
    user_id INT NULL,
    action_type NVARCHAR(50) NOT NULL,
    table_name NVARCHAR(50) NOT NULL,
    record_id INT NULL,
    old_value NVARCHAR(MAX) NULL,
    new_value NVARCHAR(MAX) NULL,
    action_date DATETIME DEFAULT GETDATE(),
    ip_address NVARCHAR(45) NULL,
    user_agent NVARCHAR(500) NULL,
    
    CONSTRAINT FK_AuditLog_Users FOREIGN KEY (user_id) 
        REFERENCES Users(user_id) ON DELETE SET NULL
);
GO

PRINT 'All tables created successfully';
GO

-- 3. DATA INSERTION 

PRINT 'data insertion...';
GO

-- 3.1 Categories
INSERT INTO Categories (category_name, description) VALUES
('Fiction', 'Novels, short stories, and fictional works'), 
('Science', 'Scientific books'),
('Technology', 'Computer science books'),
('History', 'Historical accounts'),
('Biography', 'Life stories of successful people');
PRINT 'Categories inserted: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' rows';
GO

-- 3.2 Authors
INSERT INTO Authors (first_name, last_name, email, biography) VALUES
('George', 'Orwell', 'gorwell@example.com', 'English novelist'),
('Stephen', 'Hawking', 'shawking@example.com', 'physicist'),
('Yuval', 'Harari', 'yharari@example.com', 'Historian and professor'),
('Andrew', 'Tanenbaum', 'atanenbaum@example.com', 'Computer scientist and professor'),
('Michelle', 'Obama', 'mobama@example.com', 'Former First Lady and author');
PRINT 'Authors inserted: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' rows';
GO

-- 3.3 Publishers
INSERT INTO Publishers (publisher_name, address, phone, email) VALUES
('Penguin Books', 'London, UK', '+44-20-7010-3000', 'contact@penguin.com'),
('Cambridge Press', 'Cambridge, UK', '+44-1223-358331', 'info@cambridge.org'),
('HarperCollins', 'New York, USA', '+1-212-207-7000', 'support@harpercollins.com'),
('Pearson Education', 'London, UK', '+44-20-7010-2000', 'service@pearson.com'),
('Crown Publishing', 'New York, USA', '+1-212-782-9000', 'hello@crownpub.com');
PRINT 'Publishers inserted: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' rows';
GO

-- 3.4 Books
INSERT INTO Books (isbn, title, author_id, category_id, publisher_id, publication_year, price, total_copies, available_copies, shelf_location) VALUES
('9780451524935', '1984', 1, 1, 1, 1949, 9.99, 5, 5, 'FIC-A1'),
('9780553380163', 'A Brief History of Time', 2, 2, 2, 1988, 15.99, 3, 2, 'SCI-B2'),
('9780062316097', 'Sapiens: A Brief History of Humankind', 3, 4, 3, 2011, 22.99, 4, 3, 'HIS-C3'),
('9780133591620', 'Computer Networks', 4, 3, 4, 2013, 89.99, 2, 1, 'TEC-D4'),
('9781524763138', 'Becoming', 5, 5, 5, 2018, 19.99, 6, 6, 'BIO-E5');
PRINT 'Books inserted: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' rows';
GO

-- 3.5 Members
INSERT INTO Members (first_name, last_name, email, phone, address, membership_date, membership_expiry, max_borrow_limit, current_borrowed) VALUES
('John', 'Smith', 'john.smith@email.com', '555-0101', '123 Main St, City', '2024-01-15', '2025-01-15', 5, 0),
('Emma', 'Johnson', 'emma.johnson@email.com', '555-0102', '456 Oak Ave, Town', '2024-02-20', '2025-02-20', 5, 1),
('Michael', 'Brown', 'michael.brown@email.com', '555-0103', '789 Pine Rd, Village', '2024-03-10', '2025-03-10', 3, 1),
('Sarah', 'Davis', 'sarah.davis@email.com', '555-0104', '321 Elm St, County', '2024-04-05', '2025-04-05', 5, 1),
('David', 'Wilson', 'david.wilson@email.com', '555-0105', '654 Maple Dr, District', '2024-05-12', '2025-05-12', 4, 0);
PRINT 'Members inserted: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' rows';
GO

-- 3.6 Users
INSERT INTO Users (username, password_hash, email, first_name, last_name, role) VALUES
('admin', 'hashed_password_123', 'admin@library.com', 'Admin', 'User', 'Admin'),
('librarian1', 'hashed_password_456', 'librarian1@library.com', 'Jane', 'Doe', 'Librarian'),
('librarian2', 'hashed_password_789', 'librarian2@library.com', 'Robert', 'Johnson', 'Librarian'),
('staff1', 'hashed_password_abc', 'staff1@library.com', 'Emily', 'Clark', 'Staff');
PRINT 'Users inserted: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' rows';
GO

-- 3.7 BorrowRecords
INSERT INTO BorrowRecords (book_id, member_id, borrow_date, due_date, return_date, status) VALUES
(1, 1, '2024-10-01 10:00:00', '2024-10-15', '2024-10-14 14:30:00', 'Returned'),
(2, 2, '2024-10-02 11:15:00', '2024-10-16', NULL, 'Borrowed'),
(3, 3, '2024-10-03 09:45:00', '2024-10-17', NULL, 'Borrowed'),
(4, 4, '2024-10-04 14:20:00', '2024-10-18', NULL, 'Borrowed'),
(5, 5, '2024-10-05 13:10:00', '2024-10-19', '2024-10-25 16:45:00', 'Returned');
PRINT 'BorrowRecords inserted: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' rows';
GO

-- 3.8 Fines
INSERT INTO Fines (borrow_id, member_id, amount, fine_date, status, notes) VALUES
(5, 5, 3.00, '2024-10-26', 'Unpaid', '6 days overdue @ $0.50/day');
PRINT 'Fines inserted: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' rows';
GO

-- 3.9 Reservations
INSERT INTO Reservations (book_id, member_id, reservation_date, status, expiry_date) VALUES
(1, 2, '2024-10-28 09:00:00', 'Pending', '2024-10-30 23:59:59'),
(2, 3, '2024-10-29 10:30:00', 'Confirmed', '2024-11-02 23:59:59'),
(3, 4, '2024-10-30 14:15:00', 'Cancelled', '2024-11-01 23:59:59');
PRINT 'Reservations inserted: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' rows';
GO

-- 3.10 AuditLog
INSERT INTO AuditLog (user_id, action_type, table_name, record_id, action_date) VALUES
(1, 'INSERT', 'Categories', 1, '2024-01-15 10:00:00'),
(2, 'INSERT', 'Books', 1, '2024-01-16 11:30:00'),
(1, 'UPDATE', 'Members', 2, '2024-02-20 14:45:00'),
(3, 'BORROW', 'BorrowRecords', 1, '2024-10-01 10:05:00'),
(2, 'RETURN', 'BorrowRecords', 1, '2024-10-14 14:35:00');
PRINT 'AuditLog inserted: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' rows';
GO

PRINT 'All data inserted successfully';
GO

-- 4. FUNCTIONS

-- 4.1 Calculate overdue fine
CREATE FUNCTION fn_CalculateOverdueFine(
    @BorrowID INT,
    @DailyFineRate DECIMAL(5,2) = 0.50
)
RETURNS DECIMAL(10,2)
AS
BEGIN
    DECLARE @FineAmount DECIMAL(10,2) = 0;
    DECLARE @DueDate DATE;
    DECLARE @ReturnDate DATETIME;
    DECLARE @DaysOverdue INT;
    
    SELECT @DueDate = due_date, @ReturnDate = return_date
    FROM BorrowRecords
    WHERE borrow_id = @BorrowID;
    
    IF @DueDate IS NULL
        RETURN 0;
    
    IF @ReturnDate IS NULL AND @DueDate < CAST(GETDATE() AS DATE)
        SET @DaysOverdue = DATEDIFF(DAY, @DueDate, GETDATE());
    ELSE IF @ReturnDate IS NOT NULL AND CAST(@ReturnDate AS DATE) > @DueDate
        SET @DaysOverdue = DATEDIFF(DAY, @DueDate, CAST(@ReturnDate AS DATE));
    ELSE
        SET @DaysOverdue = 0;
    
    IF @DaysOverdue > 0
        SET @FineAmount = @DaysOverdue * @DailyFineRate;
    
    RETURN @FineAmount;
END;
GO

-- 4.2 Check if member can borrow
CREATE FUNCTION fn_CanMemberBorrow(
    @MemberID INT
)
RETURNS BIT
AS
BEGIN
    DECLARE @CanBorrow BIT = 0;
    DECLARE @MembershipStatus NVARCHAR(20);
    DECLARE @CurrentBorrowed INT;
    DECLARE @MaxBorrowLimit INT;
    DECLARE @UnpaidFines DECIMAL(10,2);
    
    SELECT @MembershipStatus = membership_status,
           @MaxBorrowLimit = max_borrow_limit
    FROM Members
    WHERE member_id = @MemberID;
    
    SELECT @CurrentBorrowed = COUNT(*)
    FROM BorrowRecords
    WHERE member_id = @MemberID 
      AND status = 'Borrowed' 
      AND return_date IS NULL;
    
    SELECT @UnpaidFines = ISNULL(SUM(amount), 0)
    FROM Fines
    WHERE member_id = @MemberID AND status = 'Unpaid';
    
    IF @MembershipStatus = 'Active' 
       AND @CurrentBorrowed < @MaxBorrowLimit
       AND @UnpaidFines = 0
        SET @CanBorrow = 1;
    
    RETURN @CanBorrow;
END;
GO

-- 4.3 Get member borrowing summary
CREATE FUNCTION fn_GetMemberSummary(
    @MemberID INT
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        m.member_id,
        CONCAT(m.first_name, ' ', m.last_name) AS member_name,
        m.email,
        m.membership_status,
        m.max_borrow_limit,
        (SELECT COUNT(*) FROM BorrowRecords WHERE member_id = @MemberID) AS total_borrowed,
        (SELECT COUNT(*) FROM BorrowRecords WHERE member_id = @MemberID AND status = 'Borrowed') AS currently_borrowing,
        (SELECT ISNULL(SUM(amount), 0) FROM Fines WHERE member_id = @MemberID) AS total_fines,
        (SELECT ISNULL(SUM(amount), 0) FROM Fines WHERE member_id = @MemberID AND status = 'Unpaid') AS unpaid_fines
    FROM Members m
    WHERE m.member_id = @MemberID
);
GO

-- 4.4 Calculate book popularity score
CREATE FUNCTION fn_CalculateBookPopularity(
    @BookID INT
)
RETURNS DECIMAL(5,2)
AS
BEGIN
    DECLARE @PopularityScore DECIMAL(5,2) = 0;
    DECLARE @TimesBorrowed INT;
    DECLARE @TotalCopies INT;
    DECLARE @AvailableCopies INT;
    DECLARE @AgeInMonths INT;
    DECLARE @PublicationYear INT;
    
    SELECT @TimesBorrowed = COUNT(*)
    FROM BorrowRecords
    WHERE book_id = @BookID;
    
    SELECT @TotalCopies = total_copies,
           @AvailableCopies = available_copies,
           @PublicationYear = publication_year
    FROM Books
    WHERE book_id = @BookID;
    
    IF @PublicationYear IS NOT NULL
        SET @AgeInMonths = DATEDIFF(MONTH, DATEFROMPARTS(@PublicationYear, 1, 1), GETDATE());
    ELSE
        SET @AgeInMonths = 12;
    
    IF @AgeInMonths = 0
        SET @AgeInMonths = 1;
    
    IF @TotalCopies > 0
    BEGIN
        SET @PopularityScore = 
            (@TimesBorrowed * 2.0) - 
            (CAST(@AvailableCopies AS DECIMAL) / @TotalCopies) - 
            (CAST(@AgeInMonths AS DECIMAL) / 12.0);
    END
    
    IF @PopularityScore < 0
        SET @PopularityScore = 0;
    
    RETURN @PopularityScore;
END;
GO

-- 4.5 Get days until due
CREATE FUNCTION fn_DaysUntilDue(
    @BorrowID INT
)
RETURNS INT
AS
BEGIN
    DECLARE @DaysRemaining INT;
    DECLARE @DueDate DATE;
    DECLARE @ReturnDate DATETIME;
    
    SELECT @DueDate = due_date, @ReturnDate = return_date
    FROM BorrowRecords
    WHERE borrow_id = @BorrowID;
    
    IF @ReturnDate IS NOT NULL
        RETURN 0;
    
    SET @DaysRemaining = DATEDIFF(DAY, GETDATE(), @DueDate);
    
    IF @DaysRemaining < 0
        RETURN @DaysRemaining;
    
    RETURN @DaysRemaining;
END;
GO

PRINT 'All functions created successfully';
GO

-- 5. STORED PROCEDURES

-- 5.1 Borrow a Book
CREATE PROCEDURE sp_BorrowBook
    @BookID INT,
    @MemberID INT,
    @DueDate DATE,
    @UserID INT = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @TransactionName NVARCHAR(32) = 'BorrowBookTransaction';
    BEGIN TRANSACTION @TransactionName;
    
    BEGIN TRY
        DECLARE @AvailableCopies INT;
        SELECT @AvailableCopies = available_copies 
        FROM Books WHERE book_id = @BookID;
        
        IF @AvailableCopies <= 0
        BEGIN
            RAISERROR('Book is not available for borrowing.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN -1;
        END
        
        DECLARE @MemberStatus NVARCHAR(20);
        DECLARE @CurrentBorrowed INT;
        DECLARE @MaxBorrowLimit INT;
        
        SELECT @MemberStatus = membership_status,
               @CurrentBorrowed = current_borrowed,
               @MaxBorrowLimit = max_borrow_limit
        FROM Members WHERE member_id = @MemberID;
        
        IF @MemberStatus != 'Active'
        BEGIN
            RAISERROR('Member is not active. Status: %s', 16, 1, @MemberStatus);
            ROLLBACK TRANSACTION;
            RETURN -2;
        END
        
        IF @CurrentBorrowed >= @MaxBorrowLimit
        BEGIN
            RAISERROR('Member has reached borrowing limit (%d/%d)', 16, 1, @CurrentBorrowed, @MaxBorrowLimit);
            ROLLBACK TRANSACTION;
            RETURN -3;
        END
        
        INSERT INTO BorrowRecords (book_id, member_id, due_date, status)
        VALUES (@BookID, @MemberID, @DueDate, 'Borrowed');
        
        DECLARE @BorrowID INT = SCOPE_IDENTITY();
        
        INSERT INTO AuditLog (user_id, action_type, table_name, record_id, new_value)
        VALUES (@UserID, 'BORROW', 'BorrowRecords', @BorrowID, 
                CONCAT('BookID:', @BookID, '|MemberID:', @MemberID, '|DueDate:', @DueDate));
        
        COMMIT TRANSACTION @TransactionName;
        
        SELECT 
            'SUCCESS' AS status,
            @BorrowID AS borrow_id,
            'Book borrowed successfully' AS message,
            @DueDate AS due_date;
        
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION @TransactionName;
        
        DECLARE @ErrorMessage NVARCHAR(4000) = ERROR_MESSAGE();
        RAISERROR(@ErrorMessage, 16, 1);
        RETURN -99;
    END CATCH
END;
GO

-- 5.2 Return a Book
CREATE PROCEDURE sp_ReturnBook
    @BorrowID INT,
    @UserID INT = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @TransactionName NVARCHAR(32) = 'ReturnBookTransaction';
    BEGIN TRANSACTION @TransactionName;
    
    BEGIN TRY
        DECLARE @BookID INT;
        DECLARE @MemberID INT;
        DECLARE @DueDate DATE;
        DECLARE @ReturnDate DATETIME = GETDATE();
        DECLARE @FineAmount DECIMAL(10,2) = 0;
        DECLARE @DaysOverdue INT = 0;
        
        SELECT @BookID = book_id, @MemberID = member_id, @DueDate = due_date
        FROM BorrowRecords 
        WHERE borrow_id = @BorrowID AND status = 'Borrowed';
        
        IF @BookID IS NULL
        BEGIN
            RAISERROR('Borrow record not found or already returned.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN -1;
        END
        
        IF CAST(@ReturnDate AS DATE) > @DueDate
        BEGIN
            SET @DaysOverdue = DATEDIFF(DAY, @DueDate, @ReturnDate);
            SET @FineAmount = @DaysOverdue * 0.50;
            
            INSERT INTO Fines (borrow_id, member_id, amount, status)
            VALUES (@BorrowID, @MemberID, @FineAmount, 'Unpaid');
        END
        
        UPDATE BorrowRecords 
        SET return_date = @ReturnDate,
            status = 'Returned',
            fine_amount = @FineAmount
        WHERE borrow_id = @BorrowID;
        
        INSERT INTO AuditLog (user_id, action_type, table_name, record_id, new_value)
        VALUES (@UserID, 'RETURN', 'BorrowRecords', @BorrowID, 
                CONCAT('FineAmount:', @FineAmount, '|DaysOverdue:', @DaysOverdue));
        
        COMMIT TRANSACTION @TransactionName;
        
        SELECT 
            'SUCCESS' AS status,
            'Book returned successfully' AS message,
            @FineAmount AS fine_amount,
            @DaysOverdue AS days_overdue,
            @ReturnDate AS return_date;
        
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION @TransactionName;
        
        DECLARE @ErrorMessage NVARCHAR(4000) = ERROR_MESSAGE();
        RAISERROR(@ErrorMessage, 16, 1);
        RETURN -99;
    END CATCH
END;
GO

PRINT 'Stored procedures created successfully';
GO

-- 6. VIEWS

-- 6.1 Available Books with Details
CREATE VIEW vw_AvailableBooks AS
SELECT 
    b.book_id,
    b.isbn,
    b.title,
    a.first_name + ' ' + a.last_name AS author,
    c.category_name,
    p.publisher_name,
    b.publication_year,
    b.price,
    b.total_copies,
    b.available_copies,
    (b.total_copies - b.available_copies) AS borrowed_copies,
    b.shelf_location,
    b.book_status,
    b.created_at
FROM Books b,
     Authors a,
     Categories c,
     Publishers p
WHERE b.author_id = a.author_id
  AND b.category_id = c.category_id
  AND b.publisher_id = p.publisher_id
  AND b.available_copies > 0 
  AND b.book_status = 'Available';
GO

-- 6.2 Current Borrowings
CREATE VIEW vw_CurrentBorrowings AS
SELECT 
    br.borrow_id,
    b.title AS book_title,
    b.isbn,
    m.first_name + ' ' + m.last_name AS member_name,
    m.email AS member_email,
    m.phone AS member_phone,
    br.borrow_date,
    br.due_date,
    br.return_date,
    DATEDIFF(DAY, GETDATE(), br.due_date) AS days_remaining,
    CASE 
        WHEN br.due_date < GETDATE() THEN DATEDIFF(DAY, br.due_date, GETDATE())
        ELSE 0 
    END AS days_overdue,
    br.fine_amount,
    br.status
FROM BorrowRecords br,
     Books b,
     Members m
WHERE br.book_id = b.book_id
  AND br.member_id = m.member_id
  AND br.status IN ('Borrowed', 'Overdue');
GO

PRINT 'Views created successfully';
GO

-- 7. TRIGGERS

-- 7.1 Update book copies when borrowing
CREATE TRIGGER trg_AfterBorrowInsert
ON BorrowRecords
AFTER INSERT
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Update book copies
    UPDATE b
    SET b.available_copies = b.available_copies - r.cnt,
        b.book_status = CASE 
            WHEN (b.available_copies - r.cnt) > 0 THEN 'Available'
            ELSE 'Borrowed'
        END
    FROM Books b
    JOIN (SELECT book_id, COUNT(*) AS cnt
          FROM inserted
          WHERE status = 'Borrowed'
          GROUP BY book_id) r ON r.book_id = b.book_id;
    
    -- Update member's borrowed count
    UPDATE m
    SET m.current_borrowed = m.current_borrowed + r.cnt
    FROM Members m
    JOIN (SELECT member_id, COUNT(*) AS cnt
          FROM inserted
          WHERE status = 'Borrowed'
          GROUP BY member_id) r ON r.member_id = m.member_id;
END;
GO

-- 7.2 Update book copies when returning
CREATE TRIGGER trg_AfterBorrowUpdate
ON BorrowRecords
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Update book copies
    UPDATE b
    SET b.available_copies = b.available_copies + r.cnt,
        b.book_status = 'Available'
    FROM Books b
    JOIN (SELECT i.book_id, COUNT(*) AS cnt
          FROM inserted i
          JOIN deleted d ON d.borrow_id = i.borrow_id
          WHERE i.status = 'Returned' AND d.status <> 'Returned'
          GROUP BY i.book_id) r ON r.book_id = b.book_id;
    
    -- Update member's borrowed count
    UPDATE m
    SET m.current_borrowed = m.current_borrowed - r.cnt
    FROM Members m
    JOIN (SELECT i.member_id, COUNT(*) AS cnt
          FROM inserted i
          JOIN deleted d ON d.borrow_id = i.borrow_id
          WHERE i.status = 'Returned' AND d.status <> 'Returned'
          GROUP BY i.member_id) r ON r.member_id = m.member_id;
END;
GO

PRINT 'Triggers created successfully';
GO

-- 8. INDEXES

-- 8.1 Books Table Indexes
CREATE INDEX IX_Books_ISBN ON Books(isbn);
CREATE INDEX IX_Books_Title ON Books(title);
CREATE INDEX IX_Books_AuthorID ON Books(author_id);
CREATE INDEX IX_Books_CategoryID ON Books(category_id);
CREATE INDEX IX_Books_PublisherID ON Books(publisher_id);
CREATE INDEX IX_Books_Status ON Books(book_status);
GO

-- 8.2 Members Table Indexes
CREATE INDEX IX_Members_Email ON Members(email);
CREATE INDEX IX_Members_Status ON Members(membership_status);
CREATE INDEX IX_Members_Name ON Members(last_name, first_name);
CREATE INDEX IX_Members_Phone ON Members(phone);
GO

-- 8.3 BorrowRecords Table Indexes
CREATE INDEX IX_BorrowRecords_MemberID ON BorrowRecords(member_id);
CREATE INDEX IX_BorrowRecords_BookID ON BorrowRecords(book_id);
CREATE INDEX IX_BorrowRecords_Status ON BorrowRecords(status);
CREATE INDEX IX_BorrowRecords_DueDate ON BorrowRecords(due_date);
CREATE INDEX IX_BorrowRecords_Dates ON BorrowRecords(borrow_date, return_date);
GO

-- 8.4 Fines Table Indexes
CREATE INDEX IX_Fines_MemberID ON Fines(member_id);
CREATE INDEX IX_Fines_Status ON Fines(status);
CREATE INDEX IX_Fines_BorrowID ON Fines(borrow_id);
GO

-- 8.5 Users Table Indexes
CREATE INDEX IX_Users_Username ON Users(username);
CREATE INDEX IX_Users_Role ON Users(role);
CREATE INDEX IX_Users_Email ON Users(email);
GO

PRINT 'All indexes created successfully';
GO

-- 9. SECURITY (ROLES AND USERS)

-- 9.1 Database roles
CREATE ROLE db_admin;
CREATE ROLE db_librarian;
CREATE ROLE db_member;
GO

-- 9.2 Permissions for admin
GRANT CONTROL ON DATABASE::LibraryDB TO db_admin;
GRANT CREATE TABLE TO db_admin;
GRANT CREATE PROCEDURE TO db_admin;
GRANT CREATE VIEW TO db_admin;
GRANT ALTER ANY USER TO db_admin;
GRANT ALTER ANY ROLE TO db_admin;
GO

-- 9.3 Permissions for librarian
GRANT SELECT, INSERT, UPDATE, DELETE ON Books TO db_librarian;
GRANT SELECT, INSERT, UPDATE, DELETE ON Authors TO db_librarian;
GRANT SELECT, INSERT, UPDATE, DELETE ON Categories TO db_librarian;
GRANT SELECT, INSERT, UPDATE, DELETE ON Members TO db_librarian;
GRANT SELECT, INSERT, UPDATE, DELETE ON BorrowRecords TO db_librarian;
GRANT SELECT, INSERT, UPDATE, DELETE ON Fines TO db_librarian;
GRANT SELECT, INSERT, UPDATE, DELETE ON Reservations TO db_librarian;
GRANT EXECUTE ON OBJECT::sp_BorrowBook TO db_librarian;
GRANT EXECUTE ON OBJECT::sp_ReturnBook TO db_librarian;
GO

-- 9.4 Permissions for member
GRANT SELECT ON vw_AvailableBooks TO db_member;
GRANT SELECT ON Books TO db_member;
GRANT SELECT ON Authors TO db_member;
GRANT SELECT ON Categories TO db_member;
GO

PRINT 'Security roles and permissions created successfully';
GO

-- 10. DATA MODIFICATION EXAMPLES

PRINT 'Performing data modifications...';
GO

-- 10.1 Update member's phone number and address
UPDATE Members 
SET phone = '71-123543', 
    address = 'New Address, Beirut'
WHERE member_id = 1;
PRINT 'Member 1 updated successfully';
GO

-- 10.2 Update book price and location (CORRECTED - ensure available_copies <= total_copies)
UPDATE Books 
SET price = 29.99,
    shelf_location = 'A-15'
WHERE book_id = 4;
PRINT 'Book 4 updated successfully';
GO

-- 10.3 Update the fine status
UPDATE Fines
SET paid_date = '2025-04-04', 
    payment_method = 'cash', 
    status = 'Paid'
WHERE fine_id = 1;
PRINT 'Fine 1 updated successfully';
GO

-- 10.4 Delete cancelled reservations
DELETE FROM Reservations
WHERE status = 'Cancelled';
PRINT 'Cancelled reservations deleted';
GO

-- 10.5 Update book status based on available copies 
UPDATE Books
SET book_status = CASE 
    WHEN available_copies = 0 THEN 'Borrowed'
    ELSE 'Available'
END;
PRINT 'Book statuses updated';
GO

-- 10.6 10% increase on books published before 2020
UPDATE Books 
SET price = price * 1.10
WHERE publication_year < 2020;
PRINT 'Price updated for books before 2020';
GO

-- 11. SAMPLE QUERIES

PRINT 'Running sample queries...';
GO

--  All active members
SELECT 
    first_name + ' ' + last_name AS name,
    membership_date
FROM Members
WHERE membership_status = 'Active'
ORDER BY last_name, first_name;
GO

--  Number of books per category
SELECT 
    c.category_name, 
    COUNT(b.book_id) AS number_of_books,
    SUM(b.total_copies) AS total_copies
FROM Books b,
     Categories c
WHERE b.category_id = c.category_id
GROUP BY c.category_name;
GO

-- 1. LIST ALL AVAILABLE BOOKS
SELECT * FROM vw_AvailableBooks 
WHERE available_copies > 0 
ORDER BY title;

-- 2. SHOW CURRENTLY BORROWED BOOKS WITH MEMBER DETAILS
SELECT * FROM vw_CurrentBorrowings 
WHERE status = 'Borrowed'
ORDER BY due_date;

-- 3. FIND BOOKS BY AUTHOR
SELECT 
    b.title,
    a.first_name + ' ' + a.last_name AS author,
    c.category_name,
    b.available_copies,
    b.shelf_location
FROM Books b, Authors a, Categories c
WHERE b.author_id = a.author_id
  AND b.category_id = c.category_id
  AND a.last_name LIKE '%Orwell%'
ORDER BY b.title;

-- 4. LIST MEMBERS WITH UNPAID FINES
SELECT 
    m.member_id,
    m.first_name + ' ' + m.last_name AS member_name,
    m.email,
    m.phone,
    SUM(f.amount) AS total_unpaid_fines,
    COUNT(f.fine_id) AS number_of_fines
FROM Members m, Fines f
WHERE m.member_id = f.member_id
  AND f.status = 'Unpaid'
GROUP BY m.member_id, m.first_name, m.last_name, m.email, m.phone
HAVING SUM(f.amount) > 0
ORDER BY total_unpaid_fines DESC;

-- 5. FIND BOOK BY ISBN
SELECT * FROM Books WHERE isbn = '9780451524935';

-- 6. SHOW BOOK POPULARITY RANKING
SELECT 
    title,
    author_id,
    total_copies,
    available_copies,
    (total_copies - available_copies) AS currently_borrowed,
    dbo.fn_CalculateBookPopularity(book_id) AS popularity_score
FROM Books
ORDER BY popularity_score DESC;

-- 7. LIST MEMBERS NEARING BORROWING LIMIT
SELECT 
    member_id,
    first_name + ' ' + last_name AS member_name,
    max_borrow_limit,
    current_borrowed,
    (max_borrow_limit - current_borrowed) AS can_still_borrow
FROM Members
WHERE membership_status = 'Active'
  AND current_borrowed >= max_borrow_limit - 1
ORDER BY can_still_borrow;

-- 8. FIND BOOKS IN A SPECIFIC CATEGORY
SELECT 
    b.title,
    a.first_name + ' ' + a.last_name AS author,
    b.available_copies,
    b.publication_year,
    b.price
FROM Books b, Authors a, Categories c
WHERE b.author_id = a.author_id
  AND b.category_id = c.category_id
  AND c.category_name = 'Fiction'
  AND b.available_copies > 0
ORDER BY b.title;

-- 9. SHOW MEMBER BORROWING HISTORY
SELECT 
    m.first_name + ' ' + m.last_name AS member_name,
    b.title,
    br.borrow_date,
    br.due_date,
    br.return_date,
    br.status,
    br.fine_amount
FROM BorrowRecords br, Members m, Books b
WHERE br.member_id = m.member_id
  AND br.book_id = b.book_id
  AND m.member_id = 2
ORDER BY br.borrow_date DESC;

-- 11. FIND EXPIRED MEMBERSHIPS
SELECT 
    member_id,
    first_name + ' ' + last_name AS member_name,
    email,
    membership_date,
    membership_expiry,
    DATEDIFF(DAY, GETDATE(), membership_expiry) AS days_until_expiry
FROM Members
WHERE membership_expiry < GETDATE() 
  AND membership_status = 'Active'
ORDER BY membership_expiry;

