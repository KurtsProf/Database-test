-- Run this once to set up the database and table.
-- Example: /opt/lampp/bin/mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS jerry_database;

USE jerry_database;

CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Client information
    client_first_name  VARCHAR(100) NOT NULL,
    client_middle_name VARCHAR(100),
    client_last_name   VARCHAR(100) NOT NULL,
    client_phone       VARCHAR(30)  NOT NULL,
    client_address     VARCHAR(255),
    client_zip_code    VARCHAR(20),
    client_age         INT,
    appointment_time   DATETIME NOT NULL,

    -- Staff information (person handling the client)
    staff_first_name   VARCHAR(100) NOT NULL,
    staff_middle_name  VARCHAR(100),
    staff_last_name    VARCHAR(100) NOT NULL,
    staff_phone        VARCHAR(30),
    staff_age          INT,

    -- Clock details
    clock_model         VARCHAR(255) NOT NULL,

    -- Customer record: additional customer information
    customer_since      DATE,
    date_called         DATE,
    client_cell_phone   VARCHAR(30),
    client_email        VARCHAR(255),
    client_address2     VARCHAR(255),
    client_city         VARCHAR(100),
    client_state        VARCHAR(50),
    amount               DECIMAL(10,2),
    amount_due           DECIMAL(10,2),

    -- Customer record: movement / warranty info
    movement1_type       VARCHAR(255),
    movement1_age        VARCHAR(50),
    movement2_type       VARCHAR(255),
    movement2_age        VARCHAR(50),
    warranty1_status     VARCHAR(255),
    warranty2_status     VARCHAR(255),

    -- Customer record: service directions / notes
    service_notes        TEXT,

    -- Customer record: status / follow-up
    conference_datetime  DATETIME,
    status                VARCHAR(100),
    tech_assigned         VARCHAR(100),
    booked_by             VARCHAR(100),
    last_called_date      DATE,
    times_called          INT,
    call_result           VARCHAR(255),
    occupation             VARCHAR(100),
    latitude                DECIMAL(10,7),
    longitude               DECIMAL(10,7),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customer record: activity log entries (date / activity / regarding)
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id  INT NOT NULL,
    activity_date   DATE NOT NULL,
    activity        VARCHAR(255) NOT NULL,
    regarding        VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
);

-- Customer record: past service history entries
CREATE TABLE IF NOT EXISTS service_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id  INT NOT NULL,
    service_date    DATE,
    serviced_by      VARCHAR(100),
    description       TEXT,
    estimate           DECIMAL(10,2),
    date_in            DATE,
    delivered_date     DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
);
