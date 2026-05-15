import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'

const STYLES = `
#transfer-settings {
	max-width: 600px;
}
.transfer-empty {
	color: var(--color-text-maxcontrast, #767676);
	font-style: italic;
}
.transfer-item {
	margin: 12px 0;
	padding: 12px;
	border-radius: var(--border-radius-large, 10px);
	background: var(--color-background-dark, #ededed);
}
.transfer-item__name {
	font-weight: 600;
	margin-bottom: 4px;
	word-break: break-all;
}
.transfer-item__header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 8px;
	margin-bottom: 4px;
}
.transfer-item__cancel {
	flex-shrink: 0;
	padding: 4px 12px;
	border: 1px solid var(--color-border-maxcontrast, #ccc);
	border-radius: var(--border-radius-pill, 20px);
	background: transparent;
	color: var(--color-main-text, #222);
	font-size: 0.8em;
	cursor: pointer;
}
.transfer-item__cancel:hover {
	background: var(--color-background-hover, #f5f5f5);
}
.transfer-item__cancel:disabled {
	opacity: 0.5;
	cursor: default;
}
.transfer-item__url {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast, #767676);
	margin-bottom: 8px;
	word-break: break-all;
}
.transfer-item__bar {
	height: 6px;
	border-radius: 3px;
	background: var(--color-border, #ccc);
	overflow: hidden;
	margin-bottom: 4px;
}
.transfer-item__fill {
	height: 100%;
	border-radius: 3px;
	background: var(--color-primary, #0082c9);
	transition: width 0.5s ease;
}
.transfer-item__stats {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast, #767676);
}
`

;(function () {
	const container = document.getElementById('transfer-progress-list')
	if (!container) return

	// Inject styles
	const style = document.createElement('style')
	style.textContent = STYLES
	document.head.appendChild(style)

	const url = generateUrl('/apps/transfer/ajax/progress.php')
	const cancelUrl = generateUrl('/apps/transfer/ajax/cancel.php')
	let timer = null

	function formatBytes(bytes) {
		if (bytes === 0) return '0 B'
		const units = ['B', 'KB', 'MB', 'GB', 'TB']
		const i = Math.floor(Math.log(bytes) / Math.log(1024))
		return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i]
	}

	function escapeHTML(s) {
		const div = document.createElement('div')
		div.appendChild(document.createTextNode(s))
		return div.innerHTML
	}

	function render(transfers) {
		if (!transfers || transfers.length === 0) {
			container.innerHTML = '<p class="transfer-empty">' + escapeHTML(t('transfer', 'No active transfers.')) + '</p>'
			return
		}
		let html = ''
		for (const tr of transfers) {
			const pct = tr.total > 0 ? Math.round((tr.downloaded / tr.total) * 100) : 0
			const stats = tr.total > 0
				? formatBytes(tr.downloaded) + ' / ' + formatBytes(tr.total) + ' (' + pct + '%)'
				: formatBytes(tr.downloaded)
			html += '<div class="transfer-item">'
				+ '<div class="transfer-item__header">'
				+ '<div class="transfer-item__name">' + escapeHTML(tr.filename) + '</div>'
				+ '<button class="transfer-item__cancel" data-id="' + escapeHTML(tr.id) + '">' + escapeHTML(t('transfer', 'Cancel')) + '</button>'
				+ '</div>'
				+ '<div class="transfer-item__url">' + escapeHTML(tr.url) + '</div>'
			if (tr.total > 0) {
				html += '<div class="transfer-item__bar"><div class="transfer-item__fill" style="width:' + pct + '%"></div></div>'
			}
			html += '<div class="transfer-item__stats">' + stats + '</div>'
				+ '</div>'
		}
		container.innerHTML = html

		container.querySelectorAll('.transfer-item__cancel').forEach(function (btn) {
			btn.addEventListener('click', function () {
				const id = btn.dataset.id
				btn.disabled = true
				btn.textContent = t('transfer', 'Cancelling…')
				const xhr = new XMLHttpRequest()
				xhr.open('POST', cancelUrl)
				xhr.setRequestHeader('requesttoken', OC.requestToken)
				xhr.setRequestHeader('Content-Type', 'application/json')
				xhr.send(JSON.stringify({ transferId: id }))
			})
		})
	}

	function poll() {
		const xhr = new XMLHttpRequest()
		xhr.open('GET', url)
		xhr.setRequestHeader('requesttoken', OC.requestToken)
		xhr.onload = function () {
			if (xhr.status === 200) {
				try {
					const data = JSON.parse(xhr.responseText)
					if (data.error === 'no_cache') {
						container.innerHTML = '<p class="transfer-empty">'
							+ escapeHTML(t('transfer', 'Progress tracking requires Redis or Memcached. See the Nextcloud documentation for setup instructions.'))
							+ '</p>'
						stopPolling()
						return
					}
					const transfers = data.ocs ? data.ocs.data : data
					render(Array.isArray(transfers) ? transfers : [])
				} catch {
					render([])
				}
			} else {
				render([])
			}
		}
		xhr.onerror = function () {
			render([])
		}
		xhr.send()
	}

	function startPolling() {
		if (!timer) {
			poll()
			timer = setInterval(poll, 3000)
		}
	}

	function stopPolling() {
		if (timer) {
			clearInterval(timer)
			timer = null
		}
	}

	document.addEventListener('visibilitychange', function () {
		if (document.hidden) {
			stopPolling()
		} else {
			startPolling()
		}
	})

	startPolling()
})()
