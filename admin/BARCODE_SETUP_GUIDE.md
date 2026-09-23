# Barcode Generator & Attendance System - Setup & Usage Guide

## Overview
This system generates barcodes for each student using their LRN (Learner Reference Number) and provides a complete attendance tracking solution.

## Features

### 1. **Barcode Generator** (`barcode_generator.php`)
- Generate individual student barcodes
- Preview barcodes for all students
- Filter students by Grade, Section, or search by name/LRN
- Print individual barcodes
- Download individual barcodes as PDF
- Bulk download all visible barcodes as a single PDF

### 2. **Attendance Tracker** (`attendance_tracker.php`)
- Real-time barcode scanning interface
- Record student attendance with a barcode scanner or by entering LRN
- View today's attendance summary
- Real-time attendance list with check-in times
- Prevents duplicate attendance entries for the same day

### 3. **Attendance Report** (`attendance_report.php`)
- View attendance records by date
- Filter by Section
- Statistical summary (Total Students, Present, Absent, Attendance Rate)
- Export attendance data to CSV
- Print attendance reports

## Database Structure

The system uses the following database tables:

### `students` (existing table)
```
- id (INT) - Primary Key
- lrn (VARCHAR) - Learner Reference Number
- first_name (VARCHAR)
- last_name (VARCHAR)
- grade (VARCHAR)
- section (VARCHAR)
- track (VARCHAR)
- strand (VARCHAR)
```

### `attendance` (automatically created)
```
- id (INT) - Primary Key
- student_id (INT) - FK to students table
- lrn (VARCHAR)
- student_name (VARCHAR)
- check_in_time (DATETIME) - Auto-timestamped
- date (DATE)
```

## How to Use

### Generating Barcodes
1. Go to **Admin Dashboard** → **Barcode Generator**
2. View all students with their barcodes
3. Use filters to narrow down (Grade, Section, Search)
4. Options:
   - **Print**: Print individual student barcode
   - **Download**: Download individual barcode as PDF
   - **Print All**: Print all filtered students
   - **Download PDF**: Download all filtered students as PDF

### Recording Attendance

#### Method 1: Using Barcode Scanner
1. Go to **Admin Dashboard** → **Attendance Tracking**
2. Focus cursor in the scanner input field
3. Use barcode scanner to scan student ID card
4. Attendance is automatically recorded
5. Student added to "Today's Attendance Record" list

#### Method 2: Manual Entry
1. Go to **Admin Dashboard** → **Attendance Tracking**
2. Manually enter the LRN in the input field
3. Press Enter to record attendance

### Viewing Reports
1. Go to **Admin Dashboard** → **Attendance Report**
2. Select date to view
3. Optionally filter by Section
4. View statistics:
   - Total Students
   - Students Present
   - Students Absent
   - Attendance Rate (%)
5. Export to CSV or Print report

## Barcode Format
- **Type**: Code128 (standard barcode)
- **Data**: Student's LRN
- **Scanning**: Compatible with standard barcode scanners

## Installation Notes

### Requirements
- XAMPP/LAMP server with PHP 7.0+
- MySQL/MariaDB
- TCPDF library (already included in your project)

### Setup Steps
1. All files are ready to use - no additional installation needed
2. Database tables are auto-created on first access
3. Ensure write permissions on `/admin/` directory for file operations

### File Locations
```
/admin/
├── barcode_generator.php       (Main barcode interface)
├── generate_barcode.php         (Barcode image/PDF generator)
├── generate_bulk_barcodes.php  (Bulk PDF generator)
├── attendance_tracker.php       (Attendance recording interface)
├── attendance_report.php        (Attendance report viewer)
└── dashboard.php                (Updated with new links)
```

## Tips & Best Practices

1. **Barcode Quality**: Ensure barcodes are printed clearly with good contrast
2. **Scanner Setup**: Test barcode scanner before using for attendance
3. **Backup**: Regularly backup attendance database
4. **Best Time**: Record attendance at the beginning of each class period
5. **Verification**: Check attendance reports regularly to catch scanning errors

## Troubleshooting

**Q: Barcode not scanning?**
- Check barcode quality and contrast
- Clean scanner lens
- Ensure barcode is not damaged or bent

**Q: "Already marked as present" error?**
- Student already checked in today
- Use a different date to record again if needed

**Q: Broken barcode image?**
- Ensure TCPDF library path is correct
- Check file permissions in `/admin/` directory

**Q: Export not working?**
- Verify browser allows downloads
- Check file permissions in server

## Important Security Notes
- All pages require admin login
- Session timeout after 15 minutes of inactivity
- LRN data is sensitive - restrict access to authorized personnel only
- Regular database backups recommended

## Future Enhancements
- Multi-class attendance management
- Attendance analytics dashboard
- Automated SMS/Email notifications
- QR code support
- Time range filtering in reports
