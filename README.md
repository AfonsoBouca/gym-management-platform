# Fitness Down

A gym management platform built with PHP, SQLite, HTML, CSS, and JavaScript.

This website is based on multiple gym websites available online.

## Features

**All users:**
- [X] Register a new account.
- [X] Log in and out.
- [X] Edit their profile, including name, username, password, and profile photo.

**Members:**
- [X] Browse the schedule of available fitness classes, filtering by type, trainer, day, or time.
- [X] Enroll in and cancel enrollment from upcoming classes, subject to capacity limits.
- [X] View trainer profiles, including their specializations and the classes they teach.
- [X] Check the current availability of equipment in the main training area.
- [X] Leave ratings and reviews for classes they have attended.

**Trainers:**
- [X] Manage their public profile, including bio, specializations, and certifications.
- [X] View the roster of members enrolled in their classes.
- [X] Track and manage their assigned class schedule.

**Admins:**
- [X] Manage members and trainers (create, update, and deactivate accounts).
- [X] Manage the class catalog (create, edit, and remove classes) and assign trainers to them.
- [X] Manage equipment in the main training area (add, update availability status, and remove items).
- [X] Elevate a user to admin status.
- [X] Oversee and ensure the smooth operation of the entire system.

**Extra:**
- [X] Personal training bookings.
- [X] Class waitlist.
- [X] Membership plans.
- [X] Member progress tracking.
- [X] REST API.

## Running locally

The project requires PHP with SQLite support. From the project root, initialise
the sample database and start PHP's built-in development server:

```bash
php database/seed.php
php -S localhost:9000
```

Then open `http://localhost:9000` in a browser. The seed script creates
fictional demo accounts for the trainer, administrator, and member roles. Their
credentials are printed in the terminal when the database is initialised.

## Project structure

- `admin/`, `member/`, and `trainer/` contain role-specific interfaces.
- `api/` contains form handlers and REST-style endpoints.
- `database/` contains the SQLite schema and sample-data seed script.
- `includes/` contains shared authentication, database, and layout helpers.
- `assets/`, `css/`, and `js/` contain the front-end resources.

## Credits

The original project credits and visual assets are preserved in the repository.