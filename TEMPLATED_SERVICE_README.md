# Templated Systemd PHP Development Server Guide

This guide explains how to manage and add local development sites running persistent background PHP servers mapped to specific ports (`8001`, `8002`, etc.) on Linux using `systemd`.

---

## Directory & File Structure

| File Path | Description |
| :--- | :--- |
| `~/.config/systemd/user/dev-site@.service` | Templated systemd service definition |
| `~/.config/dev-sites/<PORT>.env` | Environment file containing the target folder path for `<PORT>` |

---

## How to Add a New Site (e.g., Port `8002`)

### Step 1: Create the Environment File
Create a new file at `~/.config/dev-sites/8002.env` with the target document root directory:

```env
SITE_DIR=/home/ahmed/projects/AnotherProject/www
```

### Step 2: Enable & Start the Service
Run the following command in your terminal:

```bash
systemctl --user enable --now dev-site@8002
```

Your new site will now immediately be available at `http://localhost:8002/` and will automatically start whenever your computer boots up.

---

## How to Edit an Existing Site's Folder

If you move a project or want a port to point to a different directory:

1. Open `~/.config/dev-sites/<PORT>.env` (e.g., `~/.config/dev-sites/8001.env`).
2. Update the `SITE_DIR` path to your new directory:
   ```env
   SITE_DIR=/home/ahmed/projects/NewMagmaPath/www
   ```
3. Restart the service:
   ```bash
   systemctl --user restart dev-site@8001
   ```

---

## Useful Service Management Commands

Replace `8001` with whichever port service you want to manage:

- **Check Service Status**:
  ```bash
  systemctl --user status dev-site@8001
  ```

- **Restart Service**:
  ```bash
  systemctl --user restart dev-site@8001
  ```

- **Stop Service**:
  ```bash
  systemctl --user stop dev-site@8001
  ```

- **Disable Auto-Start on Boot**:
  ```bash
  systemctl --user disable --now dev-site@8001
  ```

- **View Live Logs**:
  ```bash
  journalctl --user -u dev-site@8001 -f
  ```

---

## Systemd Unit File Reference (`~/.config/systemd/user/dev-site@.service`)

For reference, the templated unit file configured on your system looks like this:

```ini
[Unit]
Description=Development PHP Server on Port %i
After=network.target

[Service]
Type=simple
EnvironmentFile=%h/.config/dev-sites/%i.env
ExecStart=/usr/bin/php -S localhost:%i -t ${SITE_DIR}
Restart=always
RestartSec=3

[Install]
WantedBy=default.target
```
