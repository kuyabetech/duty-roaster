import os

structure = {
    "config": [
        "database.php",
        "config.php",
        "constants.php"
    ],

    "includes": [
        "auth.php",
        "functions.php",
        "validation.php",
        "security.php"
    ],

    "models": [
        "User.php",
        "Teacher.php",
        "Department.php",
        "Duty.php",
        "Swap.php",
        "Notification.php",
        "Report.php",
        "Audit.php"
    ],

    "controllers": [
        "AuthController.php",
        "DashboardController.php",
        "TeacherController.php",
        "DutyController.php",
        "SwapController.php",
        "ReportController.php",
        "SettingsController.php"
    ],

    "views/auth": [
        "login.php",
        "register.php"
    ],

    "views/dashboard": [
        "index.php"
    ],

    "views/teachers": [
        "index.php",
        "create.php",
        "edit.php"
    ],

    "views/duties": [
        "index.php",
        "create.php",
        "calendar.php"
    ],

    "views/swaps": [
        "index.php"
    ],

    "views/reports": [
        "index.php"
    ],

    "api": [
        "auth.php",
        "duties.php",
        "teachers.php",
        "swaps.php"
    ],

    "assets/css": [
        "styles.css",
        "admin.css"
    ],

    "assets/js": [
        "main.js",
        "dashboard.js",
        "calendar.js"
    ],

    "assets/images": [],

    "uploads/profiles": [],

    "uploads/reports": [],

    "vendor": []
}


def create_project_structure():

    for folder, files in structure.items():

        # Create directory
        os.makedirs(folder, exist_ok=True)

        print(f"Folder checked: {folder}")

        # Create files
        for file in files:
            filepath = os.path.join(folder, file)

            if not os.path.exists(filepath):
                with open(filepath, "w", encoding="utf-8") as f:
                    f.write("")

                print(f"Created: {filepath}")

            else:
                print(f"Exists: {filepath}")


    # Root files
    root_files = [
        ".htaccess",
        "index.php"
    ]

    for file in root_files:
        if not os.path.exists(file):
            with open(file, "w", encoding="utf-8") as f:
                f.write("")

            print(f"Created: {file}")

        else:
            print(f"Exists: {file}")


if __name__ == "__main__":
    create_project_structure()

    print("\nProject structure created successfully.")