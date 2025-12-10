# Practice: Update a WordPress Plugin with Composer + Git

This is a **safe practice project** so you can learn how to:

- Run `composer install`
- Update a single dependency (`wpackagist-plugin/contact-form-7`)
- Use Git branches and commits

## 1. Setup

1. Download and unzip this project somewhere on your machine.
2. Open a terminal in the project folder, e.g.:

   ```bash
   cd path/to/practice-plugin-update
   ```

3. Install dependencies (first time only):

   ```bash
   composer install
   ```

## 2. First Git commit

```bash
git init
git add .
git commit -m "Initial commit with Contact Form 7 ^5.9"
```

## 3. Create a practice branch

```bash
git checkout -b feature/update-contact-form-7
```

## 4. Update the plugin version

1. Open `composer.json` in your editor.
2. Find:

   ```json
   "wpackagist-plugin/contact-form-7": "^5.9"
   ```

3. Change it to for example:

   ```json
   "wpackagist-plugin/contact-form-7": "^6.0"
   ```

4. Save the file.

5. Back in terminal, run:

   ```bash
   composer update wpackagist-plugin/contact-form-7
   ```

This will update the plugin and modify `composer.lock`.

## 5. Commit the change

```bash
git status
git add composer.json composer.lock
git commit -m "Update Contact Form 7 to ^6.0"
```

## 6. (Optional) Push to GitHub

Create a new empty repo on GitHub, then:

```bash
git remote add origin https://github.com/YOUR-USERNAME/practice-plugin-update.git
git push -u origin feature/update-contact-form-7
```

Now you can open a Pull Request on GitHub to practice the full workflow.