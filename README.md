
# 📝 Blog API (Laravel + Sanctum)

## 📌 Overview

**Blog API** is a training project built with **Laravel 12** and **Sanctum Authentication**.
It provides a system to manage blog posts and comments with advanced features like **user mentions in comments** and **notifications**.
The project is designed as a solid foundation for larger Laravel-based applications, with testing support using **Factories** and **Seeders**.

---

## ⚙️ Features

* 🔐 **Authentication** using Laravel Sanctum
* 📝 **Posts**: Create, Read, Update, Delete
* 💬 **Comments**: Add comments on posts
* 📣 **Mentions**: Mention users in comments (`@username`) with automatic notification
* 🔔 **Notifications**: Laravel Notifications system with database storage + broadcasting (Pusher)
* 🏭 **Factories**: Generate test data (Users, Posts, Comments)
* 🧪 **Testing Ready**: Test with Postman or Laravel Tinker

---

## 🛠️ Tech Stack

* **Framework**: Laravel 12
* **Authentication**: Sanctum
* **Database**: MySQL
* **Notifications**: Database + Broadcasting (Pusher)
* **Testing**: Postman, Laravel Tinker
* **Data Seeding**: Factories & Seeders

---

## 📂 Database Schema (Relations)

* **User**

  * hasMany → Posts
  * hasMany → Comments
  * hasMany → Notifications

* **Post**

  * belongsTo → User
  * hasMany → Comments

* **Comment**

  * belongsTo → User
  * belongsTo → Post
  * may contain Mentions

* **Notification**

  * belongsTo → User (receiver)

* **Mention**

  * belongsTo → Comment
  * belongsTo → User (mentioned user)

---

## 🚀 Installation

```bash
# Clone the repository
git clone https://github.com/username/blog-api.git

# Install dependencies
composer install

# Configure environment and don't to create the database
cp .env.example .env
php artisan key:generate

# Start local server
php artisan serve
```


---

## 🧑‍💻 Usage

* Register / Login using Sanctum
* Create a Post
* Add Comments to a Post
* Mention users in comments using `@username`
* Receive Notifications when mentioned in a comment
* Retrieve all user notifications at `/api/notifications`

---

## 📬 Example API Endpoints

### Authentication
| Method | Endpoint         | Description                |
|--------|----------------|----------------------------|
| POST   | `/api/register` | Register a new user        |
| POST   | `/api/login`    | Login and get auth token   |
| POST   | `/api/logout`   | Logout the authenticated user |

### Posts
| Method | Endpoint        | Description               |
|--------|----------------|---------------------------|
| GET    | `/api/posts`    | List all posts            |
| GET    | `/api/posts/{id}` | Get a single post       |
| POST   | `/api/posts`    | Create a new post         |
| PUT    | `/api/posts/{id}` | Update a post           |
| DELETE | `/api/posts/{id}` | Delete a post           |

### Comments
| Method | Endpoint                  | Description                |
|--------|---------------------------|----------------------------|
| POST   | `/api/posts/{id}/comments` | Add a comment to a post    |
| GET    | `/api/posts/{id}/comments` | List comments for a post   |
| GET    | `/api/comments/{id}` | Get a single comment         |
| PUT    | `/api/comments/{id}` | Update a comment             |
| DELETE | `/api/comments/{id}` | Delete a comment             |

### Mentions & Notifications
- Mention users in comments using `@username`
- Notifications are sent automatically and stored in database
- Real-time broadcasting using Pusher

---

## 📸 Example with Postman

* All endpoints are fully testable via Postman.
* Factories can generate dummy data for testing.

---

## 📌 Notes

* This project is mainly for **training and practice**.
* **Likes feature is not implemented yet**.
* Future improvements may include:

  * Likes
  * Nested comment replies
  * Real-time chat