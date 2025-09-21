# Funnel

Funnel is a lightweight CRM built with PHP and SQLite. It lets small teams manage companies, contacts, deals, and sales activities with a minimalist, mobile-responsive interface.

## Features

* User registration, login, and secure password storage.
* Company, contact, deal, and activity tracking backed by SQLite.
* Inline stage management and automatic deal assignment to the creator.
* Clean, responsive UI designed to work well on desktops and phones.

## Getting started

1. **Install PHP** (8.1+ recommended) with the SQLite extension enabled.
2. **Start the built-in server** from the project root:

   ```bash
   php -S localhost:8000 -t public
   ```

3. **Open the app** in your browser at [http://localhost:8000](http://localhost:8000).

On first run, the SQLite database is created automatically in `database/crm.sqlite`. Create an account from the landing page and start logging companies, contacts, deals, and activities.
