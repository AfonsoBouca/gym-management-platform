PRAGMA foreign_keys = ON;

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    profile_photo TEXT,
    role TEXT NOT NULL CHECK (role IN ('member', 'trainer', 'admin')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active INTEGER NOT NULL DEFAULT 1
);


-- this serves to store hashed tokens for the remember me login feature
-- raw token is in users' cookies, sha256 hash stored only.

CREATE TABLE remember_me_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    expires_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    -- cascade: auto delete row in child table when corr. row in parent is deleted.    
);

CREATE TABLE trainer_profiles(
    user_id INTEGER PRIMARY KEY,
    bio TEXT,
    specializations TEXT,
    certifications TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


CREATE TABLE trainer_availability(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    trainer_id INTEGER NOT NULL,
    day_of_week TEXT NOT NULL CHECK (day_of_week IN ('monday','tuesday','wednesday','thursday','friday','saturday','sunday')),
    start_time TEXT NOT NULL,
    end_time TEXT NOT NULL,
    FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE classes(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    trainer_id INTEGER NOT NULL,
    name TEXT NOT NULL, --this name will be the type of class
    description TEXT,
    difficulty TEXT NOT NULL CHECK (difficulty IN('beginner', 'intermediate', 'advanced')),
    duration_minutes INTEGER NOT NULL,
    capacity INTEGER NOT NULL,
    photo TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE sessions(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    class_id INTEGER NOT NULL,
    day_of_week TEXT NOT NULL CHECK (day_of_week IN ('monday','tuesday','wednesday','thursday','friday','saturday','sunday')),
    start_time TEXT NOT NULL,
    duration_minutes INTEGER NOT NULL CHECK (duration_minutes > 0),
    status TEXT NOT NULL DEFAULT 'scheduled' CHECK (status IN ('scheduled', 'completed', 'cancelled')),
    notes TEXT,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

CREATE TABLE enrollments(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    member_id INTEGER NOT NULL,
    session_id INTEGER NOT NULL,
    enrolled_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status TEXT NOT NULL CHECK (status IN ('enrolled', 'cancelled', 'waitlisted')) DEFAULT 'enrolled',
    waitlist_pos INTEGER, --null if enr, 1-based if waitlist, updated by app if waitlist shifts
    attended INTEGER NOT NULL DEFAULT 0, -- 0 not attended, 1 attented

    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    UNIQUE (member_id, session_id)
);

CREATE TABLE reviews(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    enrollment_id INTEGER NOT NULL UNIQUE,
    rating INTEGER NOT NULL CHECK (rating BETWEEN 1 and 5),
    comment TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE
);


CREATE TABLE equipment(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL,
    name TEXT NOT NULL,
    status TEXT NOT NULL CHECK (status IN ('available', 'in_use', 'maintenance')),
    notes TEXT --faulty equipment, any optional details
);



CREATE TABLE membership_plans(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    price INTEGER NOT NULL CHECK (price >= 0),
    description TEXT,
    features TEXT,
    is_active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE member_subscriptions(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    member_id INTEGER NOT NULL,
    plan_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'cancelled', 'expired')),
    start_date TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    end_date TEXT,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES membership_plans(id) ON DELETE RESTRICT
);

CREATE TABLE personal_bookings(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    member_id INTEGER NOT NULL,
    trainer_id INTEGER NOT NULL,
    booking_date TEXT NOT NULL,
    start_time TEXT NOT NULL,
    end_time TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'booked' CHECK (status IN ('booked', 'cancelled', 'completed')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE (trainer_id, booking_date, start_time)
);

CREATE TABLE workout_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    member_id INTEGER NOT NULL,
    enrollment_id INTEGER, --can be null if no enrollments happen
    logged_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    duration_minutes INTEGER CHECK(duration_minutes > 0),
    calories_burnt INTEGER CHECK (calories_burnt >= 0),
    notes TEXT,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE
);

CREATE TABLE fitness_goals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    member_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    target REAL,
    unit TEXT,
    deadline TEXT,
    notes TEXT,
    status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','achieved','abandoned')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE   
);

CREATE TABLE achievements(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT NOT NULL,
    icon TEXT,
    threshold INTEGER
);

CREATE TABLE member_achievements(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    member_id INTEGER NOT NULL,
    achievement_id INTEGER NOT NULL,
    earned_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
    UNIQUE (member_id, achievement_id)
);

--may create indexes for faster query search

CREATE INDEX idx_enrollments_session  ON enrollments (session_id, status);
CREATE INDEX idx_enrollments_member   ON enrollments (member_id);
CREATE INDEX idx_sessions_class       ON sessions (class_id);
CREATE INDEX idx_personal_bookings_trainer ON personal_bookings (trainer_id, booking_date);
CREATE INDEX idx_personal_bookings_member  ON personal_bookings (member_id);
CREATE INDEX idx_workout_logs_member  ON workout_logs (member_id, logged_at);
CREATE INDEX idx_subscriptions_member ON member_subscriptions (member_id, status);
CREATE INDEX idx_remember_tokens_hash ON remember_me_tokens (token_hash);