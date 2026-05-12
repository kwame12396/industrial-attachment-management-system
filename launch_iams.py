"""
launch_iams.py — IAMS Quick Launcher
=====================================
Run this script to automatically:
  1. Find your XAMPP installation (C/D/E/F drives)
  2. Start Apache and MySQL services
  3. Import iams_db from database.sql (if not already imported)
  4. Copy the iams/ folder to htdocs (if not already there)
  5. Open http://localhost/iams/ in your default browser

Usage:
    python launch_iams.py

Requirements:
    - XAMPP installed on Windows (C:\\xampp, D:\\xampp, E:\\xampp, or F:\\xampp)
    - Python 3.7+
    - Run as Administrator for best results (needed to start services)
"""

import os
import sys
import time
import shutil
import subprocess
import webbrowser
from pathlib import Path

# ── Configuration ─────────────────────────────────────────────────────────────
XAMPP_DRIVES       = ['C', 'D', 'E', 'F', 'G']
XAMPP_SUBPATH      = 'xampp'
SITE_FOLDER        = 'iams'
DB_NAME            = 'iams_db'
DB_SQL_FILE        = 'database.sql'
SITE_URL           = 'http://localhost/iams/'
MYSQL_USER         = 'root'
MYSQL_PASS         = ''          # default XAMPP root has no password
WAIT_AFTER_START   = 5          # seconds to wait for services to initialise

# ── Helpers ───────────────────────────────────────────────────────────────────

def cprint(colour, msg):
    codes = {'green': '\033[92m', 'red': '\033[91m', 'yellow': '\033[93m',
             'cyan':  '\033[96m', 'bold': '\033[1m',  'reset': '\033[0m'}
    print(f"{codes.get(colour,'')}{msg}{codes['reset']}")

def find_xampp():
    """Return the Path to the XAMPP root directory, or None."""
    for drive in XAMPP_DRIVES:
        candidate = Path(f'{drive}:\\{XAMPP_SUBPATH}')
        if candidate.is_dir() and (candidate / 'xampp-control.exe').exists():
            return candidate
    return None

def service_running(name):
    """Return True if a Windows service or process is running."""
    result = subprocess.run(
        ['sc', 'query', name],
        capture_output=True, text=True
    )
    return 'RUNNING' in result.stdout

def start_xampp_services(xampp_root: Path):
    """Start Apache and MySQL via XAMPP CLI tool."""
    cli = xampp_root / 'xampp_cli.exe'
    httpd_exe = xampp_root / 'apache' / 'bin' / 'httpd.exe'
    mysqld_bat = xampp_root / 'mysql_start.bat'

    # Prefer xampp_cli if available
    if cli.exists():
        cprint('cyan', '  Starting Apache via xampp_cli ...')
        subprocess.Popen([str(cli), 'start', 'apache'], cwd=str(xampp_root),
                         creationflags=subprocess.CREATE_NO_WINDOW)
        cprint('cyan', '  Starting MySQL via xampp_cli ...')
        subprocess.Popen([str(cli), 'start', 'mysql'], cwd=str(xampp_root),
                         creationflags=subprocess.CREATE_NO_WINDOW)
    else:
        # Fallback: call apache_start / mysql_start batch files
        apache_bat = xampp_root / 'apache_start.bat'
        if apache_bat.exists():
            cprint('cyan', '  Starting Apache (apache_start.bat) ...')
            subprocess.Popen([str(apache_bat)], shell=True,
                             cwd=str(xampp_root),
                             creationflags=subprocess.CREATE_NO_WINDOW)
        else:
            cprint('yellow', '  apache_start.bat not found — trying net start Apache2.4')
            subprocess.run(['net', 'start', 'Apache2.4'], capture_output=True)

        if mysqld_bat.exists():
            cprint('cyan', '  Starting MySQL (mysql_start.bat) ...')
            subprocess.Popen([str(mysqld_bat)], shell=True,
                             cwd=str(xampp_root),
                             creationflags=subprocess.CREATE_NO_WINDOW)
        else:
            cprint('yellow', '  mysql_start.bat not found — trying net start MySQL')
            subprocess.run(['net', 'start', 'MySQL'], capture_output=True)

def mysql_cmd(xampp_root: Path, sql: str):
    """Run a MySQL command and return (stdout, returncode)."""
    mysql_exe = xampp_root / 'mysql' / 'bin' / 'mysql.exe'
    if not mysql_exe.exists():
        raise FileNotFoundError(f'mysql.exe not found at {mysql_exe}')
    args = [str(mysql_exe), f'-u{MYSQL_USER}']
    if MYSQL_PASS:
        args.append(f'-p{MYSQL_PASS}')
    args += ['-e', sql]
    result = subprocess.run(args, capture_output=True, text=True)
    return result.stdout, result.returncode

def database_exists(xampp_root: Path) -> bool:
    out, rc = mysql_cmd(xampp_root, f"SHOW DATABASES LIKE '{DB_NAME}';")
    return DB_NAME in out

def import_database(xampp_root: Path, sql_file: Path):
    """Create iams_db and import the SQL file."""
    mysql_exe = xampp_root / 'mysql' / 'bin' / 'mysql.exe'
    cprint('cyan', f'  Creating database {DB_NAME} ...')
    mysql_cmd(xampp_root, f'CREATE DATABASE IF NOT EXISTS `{DB_NAME}`;')
    cprint('cyan', f'  Importing {sql_file.name} ...')
    args = [str(mysql_exe), f'-u{MYSQL_USER}']
    if MYSQL_PASS:
        args.append(f'-p{MYSQL_PASS}')
    args += [DB_NAME]
    with open(sql_file, 'r', encoding='utf-8', errors='replace') as f:
        result = subprocess.run(args, stdin=f, capture_output=True, text=True)
    if result.returncode != 0:
        cprint('red', f'  MySQL import error: {result.stderr[:300]}')
    else:
        cprint('green', '  Database imported successfully.')

def copy_site_to_htdocs(xampp_root: Path, source_iams: Path):
    """Copy the iams/ folder into htdocs/ if not already there."""
    htdocs = xampp_root / 'htdocs' / SITE_FOLDER
    if htdocs.exists():
        cprint('yellow', f'  {htdocs} already exists — skipping copy.')
        cprint('yellow', '  (Delete it manually first if you want a fresh copy.)')
        return
    cprint('cyan', f'  Copying {source_iams} → {htdocs} ...')
    shutil.copytree(str(source_iams), str(htdocs))
    cprint('green', '  Site files copied.')

def find_iams_folder() -> Path | None:
    """Look for the iams/ source folder next to this script, or in common places."""
    script_dir = Path(__file__).parent.resolve()
    candidates = [
        script_dir / SITE_FOLDER,
        script_dir / 'IAMS_Complete' / SITE_FOLDER,
        script_dir.parent / SITE_FOLDER,
    ]
    for c in candidates:
        if c.is_dir() and (c / 'index.php').exists():
            return c
    return None

def find_sql_file() -> Path | None:
    script_dir = Path(__file__).parent.resolve()
    candidates = [
        script_dir / DB_SQL_FILE,
        script_dir / SITE_FOLDER / DB_SQL_FILE,
        script_dir / 'IAMS_Complete' / DB_SQL_FILE,
        script_dir.parent / DB_SQL_FILE,
    ]
    for c in candidates:
        if c.is_file():
            return c
    return None

# ── Main ──────────────────────────────────────────────────────────────────────

def main():
    os.system('color')  # enable ANSI colours on Windows terminal
    print()
    cprint('bold', '=' * 58)
    cprint('bold', '  IAMS — Industrial Attachment Management System')
    cprint('bold', '  Quick Launcher')
    cprint('bold', '=' * 58)
    print()

    # 1. Find XAMPP
    cprint('cyan', '[1/5] Locating XAMPP ...')
    xampp = find_xampp()
    if not xampp:
        cprint('red', '  ERROR: XAMPP not found on drives ' + ', '.join(XAMPP_DRIVES))
        cprint('red', '  Please install XAMPP from https://www.apachefriends.org/')
        sys.exit(1)
    cprint('green', f'  Found XAMPP at: {xampp}')
    print()

    # 2. Start services
    cprint('cyan', '[2/5] Starting Apache and MySQL ...')
    start_xampp_services(xampp)
    cprint('yellow', f'  Waiting {WAIT_AFTER_START}s for services to initialise ...')
    time.sleep(WAIT_AFTER_START)

    # Quick check via HTTP
    try:
        import urllib.request
        with urllib.request.urlopen('http://localhost/', timeout=5) as r:
            cprint('green', '  Apache is responding on http://localhost/')
    except Exception:
        cprint('yellow', '  Apache may still be starting — continuing anyway ...')
    print()

    # 3. Import database
    cprint('cyan', '[3/5] Setting up database ...')
    try:
        if database_exists(xampp):
            cprint('green', f'  Database "{DB_NAME}" already exists — skipping import.')
        else:
            sql_file = find_sql_file()
            if sql_file:
                import_database(xampp, sql_file)
            else:
                cprint('yellow', f'  "{DB_SQL_FILE}" not found next to script.')
                cprint('yellow', '  Please import it manually via phpMyAdmin.')
    except FileNotFoundError as e:
        cprint('yellow', f'  Warning: {e}')
        cprint('yellow', '  Please import the database manually via phpMyAdmin.')
    print()

    # 4. Copy site files
    cprint('cyan', '[4/5] Deploying site to htdocs ...')
    iams_src = find_iams_folder()
    if iams_src:
        copy_site_to_htdocs(xampp, iams_src)
    else:
        htdocs_target = xampp / 'htdocs' / SITE_FOLDER
        if htdocs_target.exists():
            cprint('green', '  Site already deployed in htdocs.')
        else:
            cprint('yellow', '  iams/ source folder not found next to this script.')
            cprint('yellow', f'  Please copy the iams/ folder to {htdocs_target} manually.')
    print()

    # 5. Open browser
    cprint('cyan', '[5/5] Opening browser ...')
    cprint('green', f'  Launching: {SITE_URL}')
    time.sleep(1)
    webbrowser.open(SITE_URL)
    print()

    cprint('bold', '=' * 58)
    cprint('green', '  IAMS is ready!  Visit: ' + SITE_URL)
    cprint('bold', '=' * 58)
    print()
    cprint('bold', '  Default logins:')
    print('    Coordinator : coordinator@iams.edu   / Admin@IAMS2024!')
    print('    Student     : student@example.com    / Student@2024!')
    print('    Supervisor  : supervisor@techcorp.co.bw / Super@2024!')
    print()
    input('Press Enter to exit ...')

if __name__ == '__main__':
    main()
