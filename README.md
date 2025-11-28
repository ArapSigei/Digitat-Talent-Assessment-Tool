# Digital Talent Assessment Platform

A web-based system for assessing children's digital skills, managing
assessors, generating reports, and storing assessment results.

## Features

### Kid Management

-   Register and manage kids
-   View kid profiles
-   Assign assessments to kids

### Assessor Management

-   Register assessors
-   Assign assessors to kids
-   Track assessor activity

### Assessment Module

-   Create assessments
-   Add questions and expected answers
-   Assign assessments to assessors
-   Track assessment status (pending, completed)

### Grading & Feedback

-   Assessors submit scores
-   Admins view grades and comments
-   Automatic grade calculation

### Reports

-   Generate assessment reports
-   View kid's performance history

### Authentication & Roles

-   Admin
-   Assessor
-   Kid/Parent (optional view access)

## Database Structure (Core Tables)

### kids

Stores kid details\
- id\
- name\
- age\
- gender\
- school

### assessors

Stores assessor information\
- id\
- name\
- email\
- phone

### assessments

List of assessments\
- id\
- kid_id\
- assessor_id\
- status\
- date

### assessment_results

Stores grades and comments\
- id\
- assessment_id\
- grade\
- comments

## Tech Stack

-   PHP
-   MySQL
-   HTML/CSS/JS
-   XAMPP/LAMP

## Setup Instructions

1.  Clone or download project
2.  Create database in phpMyAdmin
3.  Configure config.php
4.  Run via http://localhost/DGT/

## Support

If you need help, just ask.
