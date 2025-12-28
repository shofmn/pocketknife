# Pocketknife

A minimal PHP toolkit for simple web hosting. No database required - just upload and go.

Pocketknife provides four essential tools:
- **File Upload** (`/up`) - Public file upload endpoint
- **Link Shortener** (`/link`) - Create short links for any URL
- **Notes** (`/note`) - Create and manage simple text notes
- **Dashboard** (`/home`) - Manage uploads, short links, and notes

## Features

- **No Database** - Uses simple text files for storage
- **Minimal Dependencies** - Pure PHP, HTML, CSS, and JavaScript
- **Public Endpoints** - Upload and link shortening work without authentication
- **Simple Dashboard** - Protected admin area to manage everything

## Installation

1. **Upload Files**
   - Upload all files to your web root directory (e.g., `public_html` or `www`), including `.htaccess` in the root directory of this repository

2. **Set Permissions**
   - Make sure the `pocketknife/uploaded/` directory is writable (chmod 755 or 775)
   - Make sure the `pocketknife/home/` directory is writable (for storing shortlinks)
   - Make sure the `pocketknife/notes/` directory is writable (for storing notes)

3. **Configure Authentication**
   - Important: Make sure you create `.htaccess` and `.htpasswd` files in `pocketknife/home/` to enforce Basic Authentication for the Dashboard

4. **Verify Apache Configuration**
   - Ensure `mod_rewrite` is enabled on your Apache server
   - The `.htaccess` file in the root directory should be processed (usually enabled by default)

## Usage

### File Upload (`/up`)
- Visit `yourdomain.com/up`
- Select any file and upload
- Files are stored in `pocketknife/uploaded/` directory
- No authentication required

### Link Shortener (`/link`)
- Visit `yourdomain.com/link`
- Paste any URL and get a shortened link
- Optionally set a custom code (1-10 characters, a-z0-9 only)
- If no custom code provided, a random 5-character code is auto-generated
- Short links format: `yourdomain.com/s/abc12` (1-10 character code, custom or auto-generated)
- No authentication required

### Notes (`/note`)
- Visit `yourdomain.com/note`
- Create simple text notes with optional names
- Store up to 10,000 characters per note
- Notes are saved as timestamped `.txt` files
- Click on note text in the dashboard to view the full note in a new tab
- Edit or delete notes from the dashboard
- No authentication required

### Dashboard (`/home`)
- Visit `yourdomain.com/home`
- View all uploaded files with sizes and dates
- View all short links with access statistics
- View all notes with preview text (truncated to 60 characters)
- Edit shortlink codes or delete files, short links, and notes as needed

## Requirements

- PHP 7.0 or higher
- Apache web server with `mod_rewrite` enabled
- Write permissions on `pocketknife/uploaded`, `pocketknife/home`, and `pocketknife/notes` directories

## Security Notes

- Add htaccesss for the `/home` dashboard before deployment
- The upload endpoint is public - anyone can upload files
- The notes endpoint is public - anyone can create notes
- Regularly check and clean up uploaded files and notes
- Short links are public - anyone with the link can access it
- Note content is stored as plain text files and escaped when displayed to prevent XSS

## License

See LICENSE file for details.