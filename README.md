# 4_sem_web
E-Commerce Database Schema Documentation
Project Overview
The E-Commerce Store is a web-based platform built with PHP, MySQL, and PDO for managing an online shopping experience. It supports user registration and login, product browsing, cart management, product ratings, and admin functionalities like product addition and user role management. The application emphasizes security with CSRF protection, input sanitization, and password hashing. The database schema is designed to handle user data, product information, cart contents, and ratings, ensuring efficient data retrieval and integrity.
Database Schema
Tables
1. users
Stores user account information, including authentication details and profile data.



Field
Data Type
Constraints
Description



```id
INT
PRIMARY KEY, AUTO_INCREMENT
Unique identifier for each user


username
VARCHAR(50)
NOT NULL, UNIQUE
User's unique username


email
VARCHAR(100)
NOT NULL, UNIQUE
User's email address


password
VARCHAR(255)
NOT NULL
Hashed password


phone
VARCHAR(15)
NULL
User's phone number (optional)


address
TEXT
NULL
User's address (optional)


bio
TEXT
NULL, CHECK (LENGTH(bio) <= 500)
User's bio (optional, max 500 chars)


role
ENUM('user', 'admin')
NOT NULL, DEFAULT 'user'
User's role (user or admin)


created_at
TIMESTAMP
NOT NULL, DEFAULT CURRENT_TIMESTAMP
Account creation timestamp ```


2. products
Stores product information for the e-commerce catalog.



Field
Data Type
Constraints
Description



id
INT
PRIMARY KEY, AUTO_INCREMENT
Unique identifier for each product


name
VARCHAR(100)
NOT NULL
Product name


price
DECIMAL(10,2)
NOT NULL, CHECK (price >= 0)
Product price


description
TEXT
NULL
Product description (optional)


image
VARCHAR(255)
NOT NULL
Filename of the product image


created_at
TIMESTAMP
NOT NULL, DEFAULT CURRENT_TIMESTAMP
Product creation timestamp


3. cart
Stores items in users' shopping carts, synced with session data for logged-in users.



Field
Data Type
Constraints
Description



user_id
INT
FOREIGN KEY REFERENCES users(id) ON DELETE CASCADE
User who owns the cart item


product_id
INT
FOREIGN KEY REFERENCES products(id) ON DELETE CASCADE
Product in the cart


quantity
INT
NOT NULL, CHECK (quantity > 0 AND quantity <= 10)
Quantity of the product in the cart


added_at
TIMESTAMP
NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
Timestamp of last cart update




PRIMARY KEY (user_id, product_id)
Composite primary key


4. product_ratings
Stores user ratings for products, allowing one rating per user per product.



Field
Data Type
Constraints
Description



user_id
INT
FOREIGN KEY REFERENCES users(id) ON DELETE CASCADE
User who rated the product


product_id
INT
FOREIGN KEY REFERENCES products(id) ON DELETE CASCADE
Rated product


rating
INT
NOT NULL, CHECK (rating >= 1 AND rating <= 5)
Rating value (1 to 5)


rated_at
TIMESTAMP
NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
Timestamp of rating update




PRIMARY KEY (user_id, product_id)
Composite primary key


Relationships

users ↔ cart: One-to-many. A user can have multiple cart items, but each cart item belongs to one user. Enforced by cart.user_id foreign key with ON DELETE CASCADE to remove cart items when a user is deleted.
products ↔ cart: One-to-many. A product can appear in multiple carts, but each cart item references one product. Enforced by cart.product_id foreign key with ON DELETE CASCADE to remove cart items if a product is deleted.
users ↔ product_ratings: One-to-many. A user can rate multiple products, but each rating is tied to one user. Enforced by product_ratings.user_id foreign key with ON DELETE CASCADE.
products ↔ product_ratings: One-to-many. A product can have multiple ratings, but each rating is for one product. Enforced by product_ratings.product_id foreign key with ON DELETE CASCADE.

ER Diagram Description
The Entity-Relationship Diagram (ERD) for this schema includes four entities:

users: Attributes include id, username, email, password, phone, address, bio, role, created_at. id is the primary key.
products: Attributes include id, name, price, description, image, created_at. id is the primary key.
cart: Attributes include user_id, product_id, quantity, added_at. The composite primary key is (user_id, product_id).
product_ratings: Attributes include user_id, product_id, rating, rated_at. The composite primary key is (user_id, product_id).

Relationships:

users to cart: One-to-many, represented by a line from users.id to cart.user_id.
products to cart: One-to-many, represented by a line from products.id to cart.product_id.
users to product_ratings: One-to-many, represented by a line from users.id to product_ratings.user_id.
products to product_ratings: One-to-many, represented by a line from products.id to product_ratings.product_id.

The ERD visually depicts these entities as rectangles, with attributes listed inside, and relationships as lines connecting the entities, labeled with cardinality (e.g., 1:N).
SQL Create Statements
CREATE DATABASE ecommerce;
USE ecommerce;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(15),
    address TEXT,
    bio TEXT CHECK (LENGTH(bio) <= 500),
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL CHECK (price >= 0),
    description TEXT,
    image VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE cart (
    user_id INT,
    product_id INT,
    quantity INT NOT NULL CHECK (quantity > 0 AND quantity <= 10),
    added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE product_ratings (
    user_id INT,
    product_id INT,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    rated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

Sample SQL Queries

Fetch all products with average ratings (used in products.php):
SELECT p.*, COALESCE(AVG(pr.rating), 0) as avg_rating
FROM products p
LEFT JOIN product_ratings pr ON p.id = pr.product_id
GROUP BY p.id
ORDER BY p.created_at DESC;


Get a user’s cart contents with product details (used in cart.php):
SELECT p.id, p.name, p.price, p.image, c.quantity
FROM cart c
JOIN products p ON c.product_id = p.id
WHERE c.user_id = 1;


Update a user’s rating for a product (used in rate_product.php):
INSERT INTO product_ratings (user_id, product_id, rating)
VALUES (1, 10, 4)
ON DUPLICATE KEY UPDATE rating = 4, rated_at = CURRENT_TIMESTAMP;


Count unique products in a user’s cart (used in add_to_cart.php):
SELECT COUNT(DISTINCT product_id) as count
FROM cart
WHERE user_id = 1;


List all admins (useful for admin_dashboard.php):
SELECT id, username, email
FROM users
WHERE role = 'admin';



Indexing Recommendations
To optimize query performance, especially for frequent operations, consider the following indexes:

users:

Existing: PRIMARY KEY(id), UNIQUE(username), UNIQUE(email)
Recommended: None additional, as the unique constraints already create indexes.


products:

Existing: PRIMARY KEY(id)
Recommended:
INDEX idx_created_at (created_at): Speeds up sorting by created_at in products.php and index.php.
INDEX idx_name (name): If you plan to add search functionality by product name.




cart:

Existing: PRIMARY KEY(user_id, product_id), FOREIGN KEY(user_id), FOREIGN KEY(product_id)
Recommended:
The foreign keys already create indexes, but ensure user_id is indexed for queries filtering by user (already covered by the primary key).




product_ratings:

Existing: PRIMARY KEY(user_id, product_id), FOREIGN KEY(user_id), FOREIGN KEY(product_id)
Recommended:
INDEX idx_product_id (product_id): Improves performance for queries calculating average ratings per product (e.g., in products.php and product.php).





SQL for Indexes:
CREATE INDEX idx_created_at ON products(created_at);
CREATE INDEX idx_name ON products(name);
CREATE INDEX idx_product_id ON product_ratings(product_id);

Notes for Developers

MySQL Version: The schema assumes MySQL 5.7 or later due to the use of CHECK constraints and ENUM. For older versions, remove CHECK constraints and enforce them in the application code.
Data Integrity: ON DELETE CASCADE ensures orphaned records are removed (e.g., cart items when a product is deleted).
Scalability: For large datasets, consider partitioning product_ratings by product_id or adding a caching layer (e.g., Redis) for average ratings.
Security: The application uses PDO with prepared statements, mitigating SQL injection risks. Ensure config.php credentials are secure in production.
Future Extensions: To support features like orders or categories, add tables like orders, order_items, and categories with appropriate relationships.

This schema supports all current functionality in the provided PHP files and is extensible for future enhancements like search, checkout, or analytics.
