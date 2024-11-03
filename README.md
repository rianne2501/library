
# Project Library API

This project is a simple REST API built with PHP, Slim Framework, and JWT (JSON Web Tokens) for authentication. The API allows users to manage books, authors,
and their relationships in a library database, with endpoints for user registration, authentication, and CRUD operations on authors and books. 
It also includes token-based security with token rotation and expiration.

## Features
- **User Registration & Authentication**: Register new users and authenticate existing ones using JWT tokens.
- **CRUD Operations**: Perform create, read, update, and delete operations on authors, books, and their relationships.
- **JWT-based Security**: Access tokens with one-time usage, expiration, and rotation for enhanced security.

## Table of Contents
- [Getting Started](#getting-started)
- [Requirements](#requirements)
- [Installation](#installation)
- [User Authentication](#api-endpoints)
- [Authors](#authors)
- [Books](#books)
- [Book-Author Relationships](#br-relationships)
- [License](#license)

## Getting Started

To run this project, make sure you have a local server environment set up (e.g., using XAMPP, WAMP, or MAMP). Follow the instructions below 
to set up the project and configure the database.

## Requirements
- PHP >= 7.4
- Composer (for package management)
- Slim Framework
- Firebase JWT for PHP

## Installation

1. Clone the repository:
   ```bash
   git clone- https://github.com/rianne2501/library-api.git
   cd library-api

## User Authentication 
- POST /user/register: Register a new user.
 ``` 
     //JSON CONTENT BODY
{
    "username": "",
    "password": ""
}
```
**Response**
```
   //Register Succesfull
{
  "status": "success",
  "access_token": "[Your Token]",
  "data": null
}
```
- POST /user/auth: Authenticate a user and return an access token.
 ``` 
     //JSON CONTENT BODY
{
    "username": "",
    "password": ""
}
```
**Response**
```
 //Register Authenticate Sucess
{
  "status": "success",
  "access_token": "[Your Token]",
  "data": null
}
//Register Authenticate Failed
{
  "status": "fail",
  "access_token": null,
  "data": {
    "title": "Authentication Failed"
  }
}
```
## Author 
- POST /authors: Add a new author.
``` 
     //JSON CONTENT BODY
{
    "name": "",
    "token": "Your Token"
}
```
**Response**
```
//If Posting Authors is success
{
  "status": "success",
  "access_token": "Your Token",
  "data": null
}
// If not sucess
{
  "status": "fail",
  "access_token": null,
  "message": "Token already used or invalid"
}
```
- GET /authors/get: Retrieve all authors.
``` 
     //JSON CONTENT BODY
{
   {
  "token": Your token
}
}
```
**Response**
```
{
  "status": "success",
  "access_token": "You Token",
  "data": []
  }
```
- PUT /authors/update/{id}: Update author information by ID.
```
//Json Content
{
    "token": "Your Token",
    "name": ""
}
```
**Response**
```
{
  "status": "success",
  "access_token": "Your Token",
  "data": null
}
```
- DELETE /authors/delete/{id}: Delete an author by ID.
```
//Json Content
{
    "token": "Your Token"
}
```
**Response**
```
{
  "status": "success",
  "access_token": "Your Token",
  "data": null
}
```
## Book 
- POST /books: Add a new book.
```
//Json Content
{
    "title": "",
    "author_id": "",
    "token": "Your Token"
}
```
**Response**
```
{
  "status": "success",
  "access_token": "Your Token",
  "data": null
}
```
- GET /books/get: Retrieve all books.
```
//Json Content
{
    "token": "Your Token"
}
```
**Response**
```
{
  "status": "success",
  "access_token": "Your Token",
  "data": []
```
- PUT /books/update/{id}: Update book information by ID.
```
//Json Content
{
    "title": "",
    "author_id": "",
    "token": "Your Token"
}
```
**Respone**
```
{
  "status": "success",
  "access_token": "Your Token",
  "data": null
}
```
- DELETE /books/delete/{id}: Delete a book by ID.
```
//Json Content
{
    "token": "Your Token"
}
```
**Response**
```
{
  "status": "success",
  "access_token": "Your Token",
  "data": null
}
```
## Book-Author Relationship 
- POST /books/get_by_author: Get book using author ID.
```
//Json Content
{
    "author_id": "",
    "token": "Your Token"
}
```
**Response**
```
{
  "status": "success",
  "access_token": "Your Token",
  "data": null
}
```
**Install Dependencies with Composer**
```
composer install
```
**Access the API at http://localhost:8000.**



   
    
