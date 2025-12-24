# Pocketknife

A minimal PHP toolkit for simple web hosting. No database required - just upload and go.

Pocketknife provides three essential tools:
- **File Upload** (`/up`) - Public file upload endpoint
- **Link Shortener** (`/link`) - Create short links for any URL
- **Dashboard** (`/home`) - Manage uploads and short links

## Features

- **No Database** - Uses simple text files for storage
- **Minimal Dependencies** - Pure PHP, HTML, CSS, and JavaScript
- **Public Endpoints** - Upload and link shortening work without authentication
- **Simple Dashboard** - Protected admin area to manage everything

## Installation

1. **Upload Files**
   - Upload all files to your web root directory (e.g., `public_html` or `www`), including `.htaccess` in the root directory of this repository

2. **Set Permissions**
   - Make sure the `uploaded/` directory is writable (chmod 755 or 775)
   - Make sure the `home/` directory is writable (for storing shortlinks)

3. **Configure Authentication**
   - Important: Make sure you create `.htaccess` and `.htpasswd` files in `pocketknife/home/` to enforce Basic Authentication for the Dashboard

4. **Verify Apache Configuration**
   - Ensure `mod_rewrite` is enabled on your Apache server
   - The `.htaccess` file in the root directory should be processed (usually enabled by default)

## Usage

### File Upload (`/up`)
- Visit `yourdomain.com/up`
- Select any file and upload
- Files are stored in `/uploaded` directory
- No authentication required

### Link Shortener (`/link`)
- Visit `yourdomain.com/link`
- Paste any URL and get a shortened link
- Optionally set a custom code (1-10 characters, a-z0-9 only)
- If no custom code provided, a random 5-character code is auto-generated
- Short links format: `yourdomain.com/s/abc12` (1-10 character code, custom or auto-generated)
- No authentication required

### Dashboard (`/home`)
- Visit `yourdomain.com/home`
- View all uploaded files with sizes and dates
- View all short links with access statistics
- Edit shortlink codes or delete files and short links as needed

## Requirements

- PHP 7.0 or higher
- Apache web server with `mod_rewrite` enabled
- Write permissions on `/uploaded` and `/home` directories

## Security Notes

- Add htaccesss for the `/home` dashboard before deployment
- The upload endpoint is public - anyone can upload files
- Regularly check and clean up uploaded files
- Short links are public - anyone with the link can access it

## License

See LICENSE file for details.