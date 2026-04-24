# This website is based on multiple gym websites on the internet.

### All of our assets are in the folder assets. This includes all the images and videos used in the website, along with the classes' images.

Our CSS files are divided in a folder called `css`, with multiple folders: `base`, `components`, and `pages`, along with a `main.css` file.

In the `base folder`, it does a CSS reset and sets the foundation for our design, making it reusable.

In the `components` folder, we define all major UI components, both visually and structurally.

In the `pages` folder, we have the 3 pages requested for this delivery:
* The home page -  `home.css`, 
* the classes page - `classes.css`, and
* the authentication page - `auth.css`

In the `main.css` file, we define our architecture in order of priority.

We have 3 HTML files required for this delivery: 
* `classes.html`, 
* `index.html` , 
* `login.html`,

that serve as the primary evalution element. These files can be found directly in the project folder, shown without needing to open any folder.

* To open these HTML files, we can click on any of them, press `"Reveal in File Explorer"`, double-click it, and choose where we would like to open. We suggest opening in a browser. 

* You can also open them dragging the file into the browser search bar, next to any of the browser tabs open.

* Last but not least, you can also open it installing the Visual Studio Code extension `"Live Server"`, right-click on your HTML file, and click `"Open with Live Server"`.

# ltw12g09

## Features

**All users:**
- [ ] Register a new account.
- [ ] Log in and out.
- [ ] Edit their profile, including name, username, password, and profile photo.

**Members:**
- [ ] Browse the schedule of available fitness classes, filtering by type, trainer, day, or time.
- [ ] Enroll in and cancel enrollment from upcoming classes, subject to capacity limits.
- [ ] View trainer profiles, including their specializations and the classes they teach.
- [ ] Check the current availability of equipment in the main training area.
- [ ] Leave ratings and reviews for classes they have attended.

**Trainers:**
- [ ] Manage their public profile, including bio, specializations, and certifications.
- [ ] View the roster of members enrolled in their classes.
- [ ] Track and manage their assigned class schedule.

**Admins:**
- [ ] Manage members and trainers (create, update, and deactivate accounts).
- [ ] Manage the class catalog (create, edit, and remove classes) and assign trainers to them.
- [ ] Manage equipment in the main training area (add, update availability status, and remove items).
- [ ] Elevate a user to admin status.
- [ ] Oversee and ensure the smooth operation of the entire system.

**Extra:**
- [ ] Something extra (e.g., personal training bookings, membership plans, waitlist, ...).

## Running
Running still not implemented

    sqlite3 database/database.db < database/database.sql
    php -S localhost:9000

## Credentials

Credentials not implemented

- admin/p4s5w0rd
- member/1234
- trainer/1234