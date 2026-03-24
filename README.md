<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="img/app.svg">
    <source media="(prefers-color-scheme: light)" srcset="img/app-dark.svg">
    <img src="img/app-dark.svg" width="64" height="64" alt="Transfer">
  </picture>
</p>

<h1 align="center">Nextcloud Transfer</h1>

<p align="center">
  <strong>Upload by link</strong> — transfer files into Nextcloud from any URL,<br>
  using the full bandwidth available to your server.
</p>

<p align="center">
  <a href="https://apps.nextcloud.com/apps/transfer"><img src="https://img.shields.io/badge/Nextcloud-29–33-0082c9?logo=nextcloud&logoColor=white" alt="Nextcloud 29–33"></a>
  <a href="https://github.com/beleon/transfer/releases/latest"><img src="https://img.shields.io/github/v/release/beleon/transfer?color=2ea44f" alt="Latest release"></a>
  <a href="COPYING"><img src="https://img.shields.io/badge/license-AGPL--3.0-blue" alt="AGPL-3.0"></a>
  <a href="https://www.transifex.com/nextcloud/nextcloud/"><img src="https://img.shields.io/badge/translations-Transifex-9cf" alt="Translate on Transifex"></a>
</p>

---

## How it works

Select **Upload by link** from the **+** menu in your files view.

![Menu at the top of the files page.](img/menu.png)

Paste a URL and the filename and extension will be detected automatically.
You can optionally provide a checksum to verify the download.

![The prompt appears in the middle of the screen.](img/prompt.png)

Click **Upload** and the transfer is queued as a background job. You'll get
activity notifications when it starts, completes, or fails.

> [!TIP]
> Queued jobs run on the server's cron schedule — typically within five minutes.
> Configure your server to trigger `cron.php` more often to speed things up.

## Building

You can build with a local toolchain or entirely in a container.

**With podman** (no local Node.js needed):

    make build

**With a local toolchain** (requires Node.js 20+ and npm):

    npm ci && npm run build

Either way the output lands in `js/` and `css/`.
To create a release archive: `make dist`

## Translations

Help translate the app by joining the
[Nextcloud team on Transifex](https://www.transifex.com/nextcloud/nextcloud/).
