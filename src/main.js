import { addNewFileMenuEntry, Permission } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'
import { generateFilePath } from '@nextcloud/router'
import axios from '@nextcloud/axios'

const CLOUD_UPLOAD_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M11 20H6.5Q4.22 20 2.61 18.43 1 16.85 1 14.58 1 12.63 2.17 11.1 3.35 9.57 5.25 9.15 5.88 6.85 7.75 5.43 9.63 4 12 4 14.93 4 16.96 6.04 19 8.07 19 11 20.73 11.2 21.86 12.5 23 13.78 23 15.5 23 17.38 21.69 18.69 20.38 20 18.5 20H13V12.85L14.6 14.4L16 13L12 9L8 13L9.4 14.4L11 12.85Z" /></svg>'

// Inject styles directly — external CSS may not load depending on NC version
const STYLES = `
.transfer-overlay {
	position: fixed;
	inset: 0;
	background: rgba(0, 0, 0, 0.5);
	z-index: 10100;
	display: flex;
	align-items: center;
	justify-content: center;
}
.transfer-dialog {
	background: var(--color-main-background, #fff);
	color: var(--color-main-text, #222);
	border-radius: var(--border-radius-large, 10px);
	padding: calc(var(--default-grid-baseline, 4px) * 4);
	width: 480px;
	max-width: 90vw;
	max-height: 90vh;
	overflow-y: auto;
	box-shadow: 0 4px 24px rgba(0, 0, 0, 0.3);
}
.transfer-dialog h2 {
	margin: 0 0 calc(var(--default-grid-baseline, 4px) * 4);
	font-size: 1.2em;
	font-weight: 600;
}
.transfer-dialog__form {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline, 4px) * 4);
}
.transfer-field label {
	display: block;
	margin-bottom: 4px;
	font-size: 0.9em;
	font-weight: 500;
	color: var(--color-text-maxcontrast, #767676);
}
.transfer-field input,
.transfer-field select {
	width: 100%;
	box-sizing: border-box;
	padding: 8px 10px;
	border: 2px solid var(--color-border-maxcontrast, #ccc);
	border-radius: var(--border-radius-large, 10px);
	font-size: 0.95em;
	background: var(--color-main-background, #fff);
	color: var(--color-main-text, #222);
	min-height: var(--default-clickable-area, 34px);
}
.transfer-field input:focus,
.transfer-field select:focus {
	border-color: var(--color-primary-element, #0082c9);
	outline: none;
}
.transfer-field--row {
	display: flex;
	align-items: flex-end;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
}
.transfer-field--grow {
	flex: 1;
	min-width: 0;
}
.transfer-field--ext {
	width: 10em;
	flex-shrink: 0;
}
.transfer-dot {
	font-size: 1.2em;
	font-weight: 600;
	padding-bottom: 8px;
}
.transfer-note {
	background: var(--note-background, var(--color-background-dark, #ededed));
	border-radius: var(--border-radius-large, 10px);
	padding: calc(var(--default-grid-baseline, 4px) * 2) calc(var(--default-grid-baseline, 4px) * 3);
}
.transfer-note p {
	margin: 0;
	font-size: 0.85em;
	color: var(--color-text-maxcontrast, #767676);
}
.transfer-actions {
	display: flex;
	justify-content: flex-end;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
}
.transfer-btn {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 8px 20px;
	border-radius: var(--border-radius-pill, 20px);
	font-size: 0.9em;
	font-weight: 600;
	cursor: pointer;
	border: 2px solid transparent;
	background: var(--color-background-dark, #ededed);
	color: var(--color-main-text, #222);
	min-height: var(--default-clickable-area, 34px);
}
.transfer-btn:hover:not(:disabled) {
	background: var(--color-background-hover, #e0e0e0);
}
.transfer-btn:disabled {
	opacity: 0.5;
	cursor: default;
}
.transfer-btn--primary {
	background: var(--color-primary-element, #0082c9);
	color: var(--color-primary-element-text, #fff);
}
.transfer-btn--primary:hover:not(:disabled) {
	background: var(--color-primary-element-hover, #006aa3);
}
.transfer-btn--primary svg {
	width: 20px;
	height: 20px;
	fill: currentColor;
}
`

function injectStyles() {
	if (document.getElementById('transfer-styles')) return
	const style = document.createElement('style')
	style.id = 'transfer-styles'
	style.textContent = STYLES
	document.head.appendChild(style)
}

/**
 * Parse a URL into a name and extension.
 */
function parseFilename(url) {
	try {
		const pathname = new URL(url).pathname
		const basename = decodeURIComponent(pathname.split('/').filter(Boolean).pop() || '')
		const dot = basename.lastIndexOf('.')
		if (dot > 0) {
			return { name: basename.substring(0, dot), extension: basename.substring(dot + 1) }
		}
		return { name: basename || '', extension: '' }
	} catch {
		return { name: '', extension: '' }
	}
}

/**
 * Show the transfer dialog and return a Promise that resolves when closed.
 */
function showDialog(currentPath) {
	injectStyles()

	return new Promise((resolve) => {
		const overlay = document.createElement('div')
		overlay.className = 'transfer-overlay'

		const dialog = document.createElement('div')
		dialog.className = 'transfer-dialog'
		dialog.setAttribute('role', 'dialog')

		dialog.innerHTML = `
			<h2>${t('transfer', 'Upload by link')}</h2>
			<div class="transfer-dialog__form">
				<div class="transfer-field">
					<label for="transfer-url">${t('transfer', 'Link')}</label>
					<input id="transfer-url" type="url" placeholder="https://example.com/file.txt" />
				</div>

				<div class="transfer-field transfer-field--row">
					<div class="transfer-field transfer-field--grow">
						<label for="transfer-name">${t('transfer', 'File name')}</label>
						<input id="transfer-name" type="text" />
					</div>
					<span class="transfer-dot">.</span>
					<div class="transfer-field transfer-field--ext">
						<label for="transfer-ext">${t('transfer', 'Extension')}</label>
						<input id="transfer-ext" type="text" />
					</div>
				</div>

				<div class="transfer-note">
					<p>${t('transfer', 'Some websites provide a checksum in addition to the file. This is used after the transfer to verify that the file is not corrupted.')}</p>
				</div>

				<div class="transfer-field transfer-field--row">
					<div class="transfer-field transfer-field--ext">
						<label for="transfer-hashalgo">${t('transfer', 'Algorithm')}</label>
						<select id="transfer-hashalgo">
							<option value="">—</option>
							<option value="md5">md5</option>
							<option value="sha1">sha1</option>
							<option value="sha256">sha256</option>
							<option value="sha512">sha512</option>
						</select>
					</div>
					<div class="transfer-field transfer-field--grow">
						<label for="transfer-hash">${t('transfer', 'Checksum')}</label>
						<input id="transfer-hash" type="text" />
					</div>
				</div>

				<div class="transfer-actions">
					<button id="transfer-cancel" class="transfer-btn">${t('transfer', 'Cancel')}</button>
					<button id="transfer-submit" class="transfer-btn transfer-btn--primary" disabled>
						${CLOUD_UPLOAD_SVG}
						${t('transfer', 'Upload')}
					</button>
				</div>
			</div>
		`

		overlay.appendChild(dialog)
		document.body.appendChild(overlay)

		const urlInput = dialog.querySelector('#transfer-url')
		const nameInput = dialog.querySelector('#transfer-name')
		const extInput = dialog.querySelector('#transfer-ext')
		const hashAlgoSelect = dialog.querySelector('#transfer-hashalgo')
		const hashInput = dialog.querySelector('#transfer-hash')
		const submitBtn = dialog.querySelector('#transfer-submit')
		const cancelBtn = dialog.querySelector('#transfer-cancel')

		let nameEdited = false
		let extEdited = false

		function updateDefaults() {
			const defaults = parseFilename(urlInput.value)
			if (!nameEdited) nameInput.placeholder = defaults.name || t('transfer', 'File name')
			if (!extEdited) extInput.placeholder = defaults.extension || t('transfer', 'Extension')
			updateValidity()
		}

		function updateValidity() {
			const defaults = parseFilename(urlInput.value)
			const name = nameInput.value || defaults.name
			const ext = extInput.value || defaults.extension
			let validUrl = false
			try {
				new URL(urlInput.value)
				validUrl = true
			} catch { /* invalid */ }
			submitBtn.disabled = !(validUrl && name && ext)
		}

		urlInput.addEventListener('input', updateDefaults)
		nameInput.addEventListener('input', () => { nameEdited = nameInput.value !== ''; updateValidity() })
		extInput.addEventListener('input', () => { extEdited = extInput.value !== ''; updateValidity() })

		function close() {
			overlay.remove()
			resolve()
		}

		cancelBtn.addEventListener('click', close)
		overlay.addEventListener('click', (e) => { if (e.target === overlay) close() })

		function onKey(e) {
			if (e.key === 'Escape') {
				document.removeEventListener('keydown', onKey)
				close()
			}
		}
		document.addEventListener('keydown', onKey)

		async function submit() {
			const defaults = parseFilename(urlInput.value)
			const name = nameInput.value || defaults.name
			const ext = extInput.value || defaults.extension
			const fullName = name + '.' + ext
			const path = currentPath.replace(/\/$/, '') + '/' + fullName

			submitBtn.disabled = true
			cancelBtn.disabled = true

			try {
				await axios.post(
					generateFilePath('transfer', 'ajax', 'transfer.php'),
					{
						path,
						url: urlInput.value,
						hashAlgo: hashAlgoSelect.value,
						hash: hashInput.value,
					},
				)
				// eslint-disable-next-line no-undef
				OC.Notification.showTemporary(
					t('transfer', 'The upload is queued and will begin processing soon.'),
				)
				close()
			} catch (error) {
				const msg = (error.response && error.response.status)
					? t('transfer', 'Failed to add the upload to the queue. The server responded with status code {statusCode}.', { statusCode: error.response.status })
					: t('transfer', 'Failed to add the upload to the queue.')
				// eslint-disable-next-line no-undef
				OC.Notification.showTemporary(msg)
				submitBtn.disabled = false
				cancelBtn.disabled = false
			}
		}

		submitBtn.addEventListener('click', submit)
		dialog.addEventListener('keydown', (e) => {
			if (e.key === 'Enter' && !submitBtn.disabled) {
				e.preventDefault()
				submit()
			}
		})

		urlInput.focus()
	})
}

addNewFileMenuEntry({
	id: 'transfer',
	displayName: t('transfer', 'Upload by link'),
	iconSvgInline: CLOUD_UPLOAD_SVG,
	order: -1,
	if: (context) => (context.permissions & Permission.CREATE) !== 0,
	async handler(context) {
		showDialog(context.path || '/')
	},
})
