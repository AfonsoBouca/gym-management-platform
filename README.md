# ltw12g09

### This website is based on multiple gym websites on the internet.

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

## Running
Running is implemented, depending on your PHP location.

If you have PHP added to your system PATH:

    php database/seed.php
    php -S localhost:9000 

Else:

    (php_location) database/seed.php
    (php_location) -S localhost:9000

## Credentials

Trainer:
- carlos@fitnessdown.pt/password123

Admin:

- admin@fitnessdown.pt/password123

Member: 
- joao@test.pt/password123