export const mps = {
	populate(levelData) {
		build(levelData);
	}
}

async function build(levelData) {
	const div = document.getElementById("mpsdiv");
	for (const m of levelData.macros) {
		try {
			appendChildToElement(div, "h4", `${m.title} [${m.fileName}]`);
			
			const pre = appendChildToElement(div, "pre", "");
			const code = document.createElement("code");
			
			const text = await readTextFileAsync(`/modules/ss2/text/macros/${levelData.id}/${m.fileName}.mps`);
			code.innerHTML = highlightText(text);

			const comments = code.querySelectorAll('span.comment');
			comments.forEach(c => {
				const innerSpans = c.querySelectorAll('span');
				innerSpans.forEach(inner => {
					inner.removeAttribute('class');
				})
			})

			const greens = code.querySelectorAll('span.green');
			greens.forEach(g => {
				const text = g.textContent;
				if (m.badVars && m.badVars.includes(text)) {
					g.classList.remove("green");
					g.classList.add("red");
				}
			})

			const globalVars = code.querySelectorAll('span.globalVar');
			globalVars.forEach(gv => {
				const text = gv.textContent;
				if (m.goodVars && m.goodVars.includes(text)) {
					gv.classList.remove("red");
					gv.classList.add("green");
				}
			})

			const functionVars = code.querySelectorAll('span.functionVar');
			functionVars.forEach(fv => {
				const text = fv.textContent;
				if (m.goodVars && m.goodVars.includes(text)) {
					const next = fv.nextElementSibling;
					if (next && next.classList.contains('purple')) {
						return;
					}
					fv.classList.remove("green");
					fv.classList.add("red");
				}
			})

			pre.appendChild(code);
		}
		catch (e) {
			console.error("Did not find Macro Program Snippet file", m.fileName, e);
		}
	}
}

// Terrible AI generated code.
// Somewhat double checked by me, but idk regex
function highlightText(txt) {
	let text = txt;
	// VARIABLE=blahblah
	text = text.replace(/\b\w+(?==[^=])/g, '<span class="red">$&</span>');
	// Macro header
	text = text.replace(/Macro\s+(\w+)\s+(\w+|\?)/g, 'Macro <span class="brown">$1</span> <span class="orange">$2</span>');
	// Global var assignments
	text = text.replace(/global\s+var\s+(\w+)\s+(\w+)/g, 'global var <span class="purple">$1</span> <span class="red">$2</span>');
	// Run(Async) THIS.MACROTHINGY
	text = text.replace(/\b(Run(?:Async)?\s+)(this)\.([a-zA-Z0-9_]+)\b/gi, '$1<span class="fadedgreen">$2</span>.<span class="brown">$3</span>');
	// Run(Async) STH.MACROTHINGY
	text = text.replace(/\b(Run(?:Async)?\s+)(?!This\b)([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\b/g, '$1<span class="red globalVar">$2</span>.<span class="brown">$3</span>');
	// THIS.blahblah
	text = text.replace(/(?<=[(= ])This(?=\.)/g, '<span class="fadedgreen">$&</span>')
	// ANYTHINGELSE.blahblah
	text = text.replace(/(?<=[(= ])(?!This)[A-Za-z_]\w*(?=\.)/g, '<span class="green">$&</span>')
	// VAR sec
	text = text.replace(/\b([a-zA-Z_][a-zA-Z0-9_]*)\ssec\b/g, '<span class="green functionVar">$1</span> sec');
	// X.XX SEC
	text = text.replace(/\d+(?:\.\d+)?\ssec/g, '<span class="purple">$&</span>');
	// dosomething(VARIABLE)
	text = text.replace(/(?<=\()\s*(?!TRUE\b)(?!FALSE\b)([A-Za-z_]\w*)\b|(?<=,\s*)(?!")(?!(TRUE|FALSE)\b)([A-Za-z_]\w*)\b/gi, '<span class="green functionVar">$&</span>')
	// blah.FUNCTION()
	text = text.replace(/(?<=[\s.=])\w+(?=\()/g, '<span class="teal">$&</span>');
	// blah.EVENTORSTH
	text = text.replace(/(?<=\.)[A-Za-z_]\w*(?!\()/g, '<span class="magenta">$&</span>');
	// X TIMES
	text = text.replace(/\b\d+\s+times\b/g, '<span class="purple">$&</span>');
	// wait (FALSE)
	text = text.replace(/(?<=\bWait\s*\()\s*(True|False)(?=\s*\))/gi, '<span class="purple">$&</span>');
	// action keywords
	text = text.replace(/\b(Wait|RunAsync|global|ActionAsync|Action|Run|On|If|Send event)\b/g, '<span class="blue">$&</span>');
	// event keywords
	text = text.replace(/(?<!Send\s)\b(event|every)\b/g, '<span class="purple">$&</span>');
	// operands
	text = text.replace(/[\*\-+]|(?<![\/<])\/(?!\/)/g, '<span class="fadedgreen">$&</span>');
	// != ==
	text = text.replace(/==|!=/g, '<span class="purple">$&</span>');
	// NULL
	text = text.replace(/\bNULL\b/gi, '<span class="fadedgreen">$&</span>');
	// comments
	text = text.replace(/\/\/.*/g, '<span class="green comment">$&</span>');
	return text;
}