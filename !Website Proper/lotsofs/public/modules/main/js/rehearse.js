(function () {
	'use strict';

	const BEAT_MS = 1500;

	const list = document.getElementById('rehearseLines');
	const items = Array.from(list.children);
	const statusLine = document.getElementById('rehearseStatus');
	const voiceSelect = document.getElementById('rehearseVoice');
	const rateInput = document.getElementById('rehearseRate');
	const rateValue = document.getElementById('rehearseRateValue');
	const showText = document.getElementById('rehearseShowText');
	const synth = 'speechSynthesis' in window ? window.speechSynthesis : null;
	const placeKey = 'rehearse:' + list.dataset.scene + ':line';

	let current = -1;
	let generation = 0;
	let unlocked = false;
	let wakeLock = null;
	let wantAwake = false;
	/// Chrome drops an utterance's end event if nothing holds on to it.
	let speaking = null;

	function load(key, fallback) {
		try {
			const value = localStorage.getItem(key);
			return value === null ? fallback : value;
		} catch (e) {
			return fallback;
		}
	}

	function save(key, value) {
		try {
			localStorage.setItem(key, value);
		} catch (e) {
		}
	}

	function setStatus(text) {
		statusLine.textContent = text;
	}

	function lineLabel() {
		return 'Line ' + (current + 1) + ' of ' + items.length;
	}

	function settled() {
		if (current < 0) {
			setStatus('You open the scene. Say your line, then press Next line.');
		} else if (current === items.length - 1) {
			setStatus(lineLabel() + ': end of scene.');
		} else {
			setStatus(lineLabel() + ': your line.');
		}
	}

	/// A line as [{say}, {beat}, ...]: a direction is a beat only when something is said after it.
	function stepsFor(item) {
		const steps = [];
		let pendingBeat = false;
		for (const span of item.children) {
			if (span.classList.contains('rehearseDirection')) {
				pendingBeat = true;
				continue;
			}
			if (pendingBeat) {
				steps.push({beat: true});
				pendingBeat = false;
			}
			/// One utterance per sentence: Chrome cuts a long one off after about 15 seconds.
			for (const sentence of span.textContent.replace(/([.!?]+)\s+/g, '$1\n').split('\n')) {
				if (sentence.trim() !== '') {
					steps.push({say: sentence.trim()});
				}
			}
		}
		return steps;
	}

	function chosenVoice() {
		if (!synth || voiceSelect.value === '') {
			return null;
		}
		return synth.getVoices().find((voice) => voice.voiceURI === voiceSelect.value) || null;
	}

	function utterance(text) {
		const spoken = new SpeechSynthesisUtterance(text);
		const voice = chosenVoice();
		if (voice) {
			spoken.voice = voice;
			spoken.lang = voice.lang;
		} else {
			spoken.lang = 'en-US';
		}
		spoken.rate = parseFloat(rateInput.value) || 1;
		return spoken;
	}

	function stopSpeaking() {
		generation++;
		speaking = null;
		if (synth) {
			synth.cancel();
		}
	}

	/// iOS only lets a page speak later on if it has spoken once inside a tap.
	function unlock() {
		if (synth && !unlocked) {
			unlocked = true;
			synth.speak(new SpeechSynthesisUtterance(''));
		}
	}

	async function keepAwake() {
		wantAwake = true;
		if (wakeLock || !('wakeLock' in navigator) || document.visibilityState !== 'visible') {
			return;
		}
		try {
			wakeLock = await navigator.wakeLock.request('screen');
			wakeLock.addEventListener('release', () => {
				wakeLock = null;
			});
		} catch (e) {
		}
	}

	function highlight() {
		items.forEach((item, index) => {
			const isCurrent = index === current;
			item.classList.toggle('rehearseCurrent', isCurrent);
			if (isCurrent) {
				item.setAttribute('aria-current', 'step');
			} else {
				item.removeAttribute('aria-current');
			}
		});
		if (current >= 0) {
			items[current].scrollIntoView({block: 'center', behavior: 'smooth'});
		} else {
			window.scrollTo({top: 0, behavior: 'smooth'});
		}
	}

	function goTo(index, speak) {
		stopSpeaking();
		current = Math.max(-1, Math.min(items.length - 1, index));
		save(placeKey, String(current));
		highlight();
		if (speak && current >= 0) {
			play(current);
		} else {
			settled();
		}
	}

	function play(index) {
		const mine = generation;
		const steps = stepsFor(items[index]);
		let step = 0;

		setStatus(lineLabel() + ': speaking…');
		if (!synth) {
			settled();
			return;
		}
		unlock();

		const advance = () => {
			if (mine !== generation) {
				return;
			}
			if (step >= steps.length) {
				speaking = null;
				settled();
				return;
			}
			const next = steps[step++];
			if (next.beat) {
				setTimeout(advance, BEAT_MS);
				return;
			}
			let finished = false;
			const done = () => {
				if (!finished) {
					finished = true;
					advance();
				}
			};
			speaking = utterance(next.say);
			speaking.onend = done;
			speaking.onerror = done;
			synth.speak(speaking);
		};
		advance();
	}

	function act(action) {
		keepAwake();
		action();
	}

	const next = () => act(() => {
		if (current < items.length - 1) {
			goTo(current + 1, true);
		}
	});
	const back = () => act(() => goTo(current - 1, true));
	const repeat = () => act(() => {
		if (current >= 0) {
			goTo(current, true);
		}
	});

	function fillVoices() {
		if (!synth) {
			return;
		}
		const wanted = load('rehearse:voice', voiceSelect.value);
		const voices = synth.getVoices().slice().sort((a, b) => {
			const aEnglish = a.lang.toLowerCase().startsWith('en') ? 0 : 1;
			const bEnglish = b.lang.toLowerCase().startsWith('en') ? 0 : 1;
			return aEnglish - bEnglish || a.name.localeCompare(b.name);
		});
		voiceSelect.length = 1;
		for (const voice of voices) {
			voiceSelect.add(new Option(voice.name + ' (' + voice.lang + ')', voice.voiceURI));
		}
		voiceSelect.value = voices.some((voice) => voice.voiceURI === wanted) ? wanted : '';
	}

	function showRate() {
		rateValue.textContent = parseFloat(rateInput.value).toFixed(1) + '×';
	}

	function applyShowText() {
		list.classList.toggle('rehearseBlurred', !showText.checked);
	}

	document.getElementById('rehearseNext').addEventListener('click', next);
	document.getElementById('rehearseBack').addEventListener('click', back);
	document.getElementById('rehearseRepeat').addEventListener('click', repeat);
	document.getElementById('rehearseRestart').addEventListener('click', () => act(() => goTo(-1, false)));

	list.addEventListener('click', (event) => {
		const item = event.target.closest('li');
		if (item && list.contains(item)) {
			act(() => goTo(items.indexOf(item), true));
		}
	});

	document.addEventListener('keydown', (event) => {
		if (event.ctrlKey || event.metaKey || event.altKey) {
			return;
		}
		const tag = event.target.tagName;
		if (tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA') {
			return;
		}
		/// A focused button already answers Space and Enter with a click.
		if ((event.key === ' ' || event.key === 'Enter') && (tag === 'BUTTON' || tag === 'SUMMARY')) {
			return;
		}
		const action = {' ': next, 'Enter': next, 'ArrowRight': next, 'ArrowLeft': back, 'r': repeat, 'R': repeat}[event.key];
		if (action) {
			event.preventDefault();
			action();
		}
	});

	voiceSelect.addEventListener('change', () => save('rehearse:voice', voiceSelect.value));
	rateInput.addEventListener('input', () => {
		showRate();
		save('rehearse:rate', rateInput.value);
	});
	showText.addEventListener('change', () => {
		applyShowText();
		save('rehearse:showText', showText.checked ? '1' : '0');
	});

	document.addEventListener('visibilitychange', () => {
		if (wantAwake && document.visibilityState === 'visible') {
			keepAwake();
		}
	});
	window.addEventListener('pagehide', stopSpeaking);

	if (synth) {
		fillVoices();
		synth.addEventListener('voiceschanged', fillVoices);
	} else {
		document.getElementById('rehearseNoSpeech').hidden = false;
	}

	rateInput.value = load('rehearse:rate', rateInput.value);
	showRate();
	showText.checked = load('rehearse:showText', '1') === '1';
	applyShowText();

	const savedPlace = parseInt(load(placeKey, '-1'), 10);
	current = Number.isInteger(savedPlace) ? Math.max(-1, Math.min(items.length - 1, savedPlace)) : -1;
	if (current >= 0) {
		highlight();
	}
	settled();
})();
