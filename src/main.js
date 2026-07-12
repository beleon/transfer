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
.transfer-actions {
	display: flex;
	justify-content: flex-end;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
}
.transfer-option {
	margin-top: calc(var(--default-grid-baseline, 4px) * 2);
}
.transfer-checkbox {
	display: flex;
	align-items: center;
	gap: 8px;
	cursor: pointer;
	font-size: 0.95em;
}
.transfer-checkbox input {
	width: 18px;
	height: 18px;
	margin: 0;
	cursor: pointer;
}
.transfer-info-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 18px;
	height: 18px;
	border-radius: 50%;
	border: 1px solid var(--color-border-maxcontrast, #ccc);
	background: transparent;
	color: var(--color-text-maxcontrast, #767676);
	font-size: 0.7em;
	font-weight: 600;
	cursor: pointer;
	flex-shrink: 0;
	padding: 0;
}
.transfer-info-btn:hover {
	background: var(--color-background-hover, #f5f5f5);
}
.transfer-info-detail {
	margin: 6px 0 0 26px;
	font-size: 0.85em;
	color: var(--color-text-maxcontrast, #767676);
}
.transfer-info-detail p {
	margin: 0;
}
.transfer-collapsible {
	margin-top: calc(var(--default-grid-baseline, 4px) * 2);
}
.transfer-collapsible__toggle {
	display: flex;
	align-items: center;
	gap: 6px;
	background: none;
	border: none;
	padding: 0;
	font-size: 0.9em;
	color: var(--color-text-maxcontrast, #767676);
	cursor: pointer;
}
.transfer-collapsible__toggle:hover {
	color: var(--color-main-text, #222);
}
.transfer-collapsible__arrow {
	font-size: 0.85em;
	transition: transform 0.15s ease;
}
.transfer-collapsible__arrow--open {
	transform: rotate(90deg);
}
.transfer-collapsible__body {
	margin-top: calc(var(--default-grid-baseline, 4px) * 2);
	padding-left: 4px;
}
.transfer-note__text {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast, #767676);
	margin: 0 0 calc(var(--default-grid-baseline, 4px) * 2) 0;
}
.transfer-settings-hint {
	font-size: 0.8em;
	color: var(--color-text-maxcontrast, #767676);
	margin: calc(var(--default-grid-baseline, 4px) * 2) 0 0 0;
}
.transfer-progress {
	display: none;
	margin-top: calc(var(--default-grid-baseline, 4px) * 4);
}
.transfer-progress__bar {
	height: 6px;
	border-radius: 3px;
	background: var(--color-border, #ccc);
	overflow: hidden;
	margin-bottom: 6px;
}
.transfer-progress__fill {
	height: 100%;
	border-radius: 3px;
	background: var(--color-primary, #0082c9);
	transition: width 0.5s ease;
	width: 0%;
}
.transfer-progress__text {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast, #767676);
}
.transfer-dl__label {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast, #767676);
	margin-bottom: 2px;
}
.transfer-dl__value {
	word-break: break-all;
	font-size: 0.95em;
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
 * Parse a filename from a URL path.
 */
function parseFilename(url) {
	try {
		const pathname = new URL(url).pathname
		const basename = decodeURIComponent(pathname.split('/').filter(Boolean).pop() || '')
		const dot = basename.lastIndexOf('.')
		if (dot > 0) {
			return { filename: basename, hasExtension: true }
		}
		return { filename: basename || '', hasExtension: false }
	} catch {
		return { filename: '', hasExtension: false }
	}
}

/**
 * Probe a URL server-side to detect the file extension from Content-Type.
 */
let probeTimer = null
function probeExtension(url, callback) {
	clearTimeout(probeTimer)
	probeTimer = setTimeout(async () => {
		try {
			const resp = await axios.get(
				generateFilePath('transfer', 'ajax', 'probe.php'),
				{ params: { url } },
			)
			callback(resp.data.extension || '')
		} catch {
			callback('')
		}
	}, 500)
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
			<div class="transfer-dialog__form" id="transfer-form">
				<div class="transfer-field">
					<label for="transfer-url">${t('transfer', 'Link')}</label>
					<input id="transfer-url" type="url" placeholder="https://example.com/file.txt" />
				</div>

				<div class="transfer-field">
					<label for="transfer-filename">${t('transfer', 'File name')}</label>
					<input id="transfer-filename" type="text" />
				</div>

				<div class="transfer-collapsible">
					<button type="button" class="transfer-collapsible__toggle" id="transfer-hash-toggle">
						<span class="transfer-collapsible__arrow" id="transfer-hash-arrow">▸</span>
						${t('transfer', 'Verify checksum')}
					</button>
					<div id="transfer-hash-section" class="transfer-collapsible__body" style="display:none">
						<p class="transfer-note__text">${t('transfer', 'Some websites provide a checksum to verify the file is not corrupted.')}</p>
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
					</div>
				</div>

				<div id="transfer-immediate-option" class="transfer-option">
					<label class="transfer-checkbox">
						<input id="transfer-immediate" type="checkbox" />
						<span>${t('transfer', 'Transfer immediately')}</span>
						<button type="button" class="transfer-info-btn" id="transfer-info-toggle">?</button>
					</label>
					<div id="transfer-info-detail" class="transfer-info-detail" style="display:none">
						<p>${t('transfer', 'Starts the transfer right away instead of waiting for the next background job cycle. Keep this page open until it finishes. Leaving or closing the page cancels the transfer.')}</p>
					</div>
				</div>

				<p class="transfer-settings-hint">${t('transfer', 'Queued transfers can be tracked under Settings > Transfer.')}</p>

				<div class="transfer-actions">
					<button id="transfer-cancel" class="transfer-btn">${t('transfer', 'Cancel')}</button>
					<button id="transfer-submit" class="transfer-btn transfer-btn--primary" disabled>
						${CLOUD_UPLOAD_SVG}
						${t('transfer', 'Upload')}
					</button>
				</div>
			</div>

			<div class="transfer-dialog__form" id="transfer-downloading" style="display:none">
				<div class="transfer-field">
					<div class="transfer-dl__label">${t('transfer', 'File name')}</div>
					<div id="transfer-dl-filename" class="transfer-dl__value"></div>
				</div>
				<div class="transfer-field">
					<div class="transfer-dl__label">${t('transfer', 'Link')}</div>
					<div id="transfer-dl-url" class="transfer-dl__value"></div>
				</div>

				<div class="transfer-progress" style="display:block">
					<div class="transfer-progress__bar">
						<div id="transfer-progress-fill" class="transfer-progress__fill"></div>
					</div>
					<div id="transfer-progress-text" class="transfer-progress__text">${t('transfer', 'Starting…')}</div>
				</div>

				<div class="transfer-actions">
					<button id="transfer-dl-cancel" class="transfer-btn">${t('transfer', 'Cancel')}</button>
				</div>
			</div>
		`

		overlay.appendChild(dialog)
		document.body.appendChild(overlay)

		const urlInput = dialog.querySelector('#transfer-url')
		const filenameInput = dialog.querySelector('#transfer-filename')
		const hashAlgoSelect = dialog.querySelector('#transfer-hashalgo')
		const hashInput = dialog.querySelector('#transfer-hash')
		const submitBtn = dialog.querySelector('#transfer-submit')
		const cancelBtn = dialog.querySelector('#transfer-cancel')
		const immediateCheckbox = dialog.querySelector('#transfer-immediate')
		const immediateOption = dialog.querySelector('#transfer-immediate-option')
		const formSection = dialog.querySelector('#transfer-form')
		const downloadingSection = dialog.querySelector('#transfer-downloading')
		const dlFilename = dialog.querySelector('#transfer-dl-filename')
		const dlUrl = dialog.querySelector('#transfer-dl-url')
		const progressFill = dialog.querySelector('#transfer-progress-fill')
		const progressText = dialog.querySelector('#transfer-progress-text')
		const dlCancelBtn = dialog.querySelector('#transfer-dl-cancel')

		// Hide immediate transfer option if no distributed cache is configured
		axios.get(generateFilePath('transfer', 'ajax', 'progress.php')).then((resp) => {
			const data = resp.data.ocs ? resp.data.ocs.data : resp.data
			if (data.error === 'no_cache') {
				immediateOption.style.display = 'none'
			}
		}).catch(() => {})

		// Toggle hash section
		const hashToggle = dialog.querySelector('#transfer-hash-toggle')
		const hashSection = dialog.querySelector('#transfer-hash-section')
		const hashArrow = dialog.querySelector('#transfer-hash-arrow')
		hashToggle.addEventListener('click', () => {
			const open = hashSection.style.display === 'none'
			hashSection.style.display = open ? 'block' : 'none'
			hashArrow.classList.toggle('transfer-collapsible__arrow--open', open)
		})

		// Toggle info detail (prevent click from toggling checkbox)
		const infoToggle = dialog.querySelector('#transfer-info-toggle')
		const infoDetail = dialog.querySelector('#transfer-info-detail')
		infoToggle.addEventListener('click', (e) => {
			e.preventDefault()
			e.stopPropagation()
			infoDetail.style.display = infoDetail.style.display === 'none' ? 'block' : 'none'
		})

		// The settings hint only applies to queued transfers
		const settingsHint = dialog.querySelector('.transfer-settings-hint')
		immediateCheckbox.addEventListener('change', () => {
			settingsHint.style.display = immediateCheckbox.checked ? 'none' : ''
		})

		let filenameEdited = false
		let probedExtension = ''
		let progressTimer = null
		let transferId = null
		let userCancelled = false

		function cancelTransfer() {
			if (transferId) {
				const id = transferId
				transferId = null
				userCancelled = true
				axios.post(
					generateFilePath('transfer', 'ajax', 'cancel.php'),
					{ transferId: id },
				).catch(() => {})
			}
		}

		function isDownloading() {
			return downloadingSection.style.display !== 'none'
		}

		function formatBytes(bytes) {
			if (bytes === 0) return '0 B'
			const units = ['B', 'KB', 'MB', 'GB', 'TB']
			const i = Math.floor(Math.log(bytes) / Math.log(1024))
			return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i]
		}

		function updateDefaults() {
			const parsed = parseFilename(urlInput.value)
			if (!filenameEdited) {
				if (parsed.hasExtension) {
					filenameInput.placeholder = parsed.filename
				} else if (parsed.filename) {
					// No extension in URL — probe the server
					filenameInput.placeholder = parsed.filename
					probeExtension(urlInput.value, (ext) => {
						probedExtension = ext
						if (!filenameEdited && ext) {
							filenameInput.placeholder = parsed.filename + '.' + ext
						}
					})
				} else {
					filenameInput.placeholder = t('transfer', 'File name')
				}
			}
			updateValidity()
		}

		function getFilename() {
			if (filenameInput.value) return filenameInput.value
			const parsed = parseFilename(urlInput.value)
			if (parsed.hasExtension) return parsed.filename
			if (parsed.filename && probedExtension) return parsed.filename + '.' + probedExtension
			return parsed.filename
		}

		function updateValidity() {
			let validUrl = false
			try {
				new URL(urlInput.value)
				validUrl = true
			} catch { /* invalid */ }
			// A checksum without an algorithm cannot be verified
			const hashOk = hashInput.value.trim() === '' || hashAlgoSelect.value !== ''
			submitBtn.disabled = !(validUrl && getFilename() && hashOk)
		}

		urlInput.addEventListener('input', updateDefaults)
		filenameInput.addEventListener('input', () => { filenameEdited = filenameInput.value !== ''; updateValidity() })
		hashInput.addEventListener('input', updateValidity)
		hashAlgoSelect.addEventListener('change', updateValidity)

		function close() {
			if (progressTimer) clearInterval(progressTimer)
			cancelTransfer()
			document.removeEventListener('keydown', onKey)
			overlay.remove()
			resolve()
		}

		cancelBtn.addEventListener('click', close)
		dlCancelBtn.addEventListener('click', close)
		// While a transfer is running, only the explicit Cancel button closes
		// the dialog. Escape or a stray click outside must not kill it.
		overlay.addEventListener('click', (e) => { if (e.target === overlay && !isDownloading()) close() })

		function onKey(e) {
			if (e.key === 'Escape' && !isDownloading()) {
				close()
			}
		}
		document.addEventListener('keydown', onKey)

		async function submit() {
			const filename = getFilename()
			const path = currentPath.replace(/\/$/, '') + '/' + filename
			const immediate = immediateCheckbox.checked

			submitBtn.disabled = true
			cancelBtn.disabled = true

			if (immediate) {
				// Step 1: Prepare — get transferId
				let prepareResp
				try {
					prepareResp = await axios.post(
						generateFilePath('transfer', 'ajax', 'prepare.php'),
						{
							path,
							url: urlInput.value,
							hashAlgo: hashAlgoSelect.value,
							hash: hashInput.value,
						},
					)
				} catch (error) {
					// eslint-disable-next-line no-undef
					OC.Notification.showTemporary(t('transfer', 'Transfer failed.'))
					submitBtn.disabled = false
					cancelBtn.disabled = false
					return
				}

				transferId = prepareResp.data.transferId

				// Switch to download view
				formSection.style.display = 'none'
				downloadingSection.style.display = 'block'
				dlFilename.textContent = filename
				dlUrl.textContent = urlInput.value

				// Start polling with heartbeat
				progressTimer = setInterval(async () => {
					try {
						const resp = await axios.get(
							generateFilePath('transfer', 'ajax', 'progress.php'),
							{ params: { heartbeat: transferId } },
						)
						const transfers = resp.data
						if (Array.isArray(transfers)) {
							const tr = transfers.find(x => x.id === transferId)
							if (tr) {
								if (tr.total > 0) {
									const pct = Math.round((tr.downloaded / tr.total) * 100)
									progressFill.style.width = pct + '%'
									progressText.textContent = formatBytes(tr.downloaded) + ' / ' + formatBytes(tr.total) + ' (' + pct + '%)'
								} else {
									progressText.textContent = formatBytes(tr.downloaded)
								}
							}
						}
					} catch { /* ignore polling errors */ }
				}, 2000)

				// Step 2: Start — blocks until download completes
				try {
					await axios.post(
						generateFilePath('transfer', 'ajax', 'start.php'),
						{ transferId },
					)
					if (progressTimer) clearInterval(progressTimer)
					transferId = null
					// eslint-disable-next-line no-undef
					OC.Notification.showTemporary(t('transfer', 'The file has been transferred successfully.'))
					close()
				} catch (error) {
					if (progressTimer) clearInterval(progressTimer)
					transferId = null
					// The user cancelled: the dialog is already gone and the
					// rejected start request is expected, not a failure.
					if (userCancelled) {
						return
					}
					const msg = (error.response && error.response.status)
						? t('transfer', 'Transfer failed. The server responded with status code {statusCode}.', { statusCode: error.response.status })
						: t('transfer', 'Transfer failed.')
					// eslint-disable-next-line no-undef
					OC.Notification.showTemporary(msg)
					downloadingSection.style.display = 'none'
					formSection.style.display = 'block'
					progressFill.style.width = '0%'
					submitBtn.innerHTML = `${CLOUD_UPLOAD_SVG} ${t('transfer', 'Upload')}`
					submitBtn.disabled = false
					cancelBtn.disabled = false
				}
			} else {
				// Background queue
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
					OC.Notification.showTemporary(t('transfer', 'The transfer is queued and will begin processing soon.'))
					close()
				} catch (error) {
					const msg = (error.response && error.response.status)
						? t('transfer', 'Transfer failed. The server responded with status code {statusCode}.', { statusCode: error.response.status })
						: t('transfer', 'Transfer failed.')
					// eslint-disable-next-line no-undef
					OC.Notification.showTemporary(msg)
					submitBtn.disabled = false
					cancelBtn.disabled = false
				}
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
