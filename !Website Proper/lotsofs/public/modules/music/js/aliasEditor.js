const ALIAS_ADD_ENDPOINT = "/music/ajax/alias-add";
const ALIAS_DELETE_ENDPOINT = "/music/ajax/alias-delete";

/* A card's other names in edit mode, each with a button to make it the current
   name and one to delete it, and a + under them that opens a box to write in
   another, as the song card's artists and albums wait behind theirs. Shared by
   the song card (songs.js) and the album and artist cards (albumCard.js), each of
   which says how to make a name current and what to do with the answer. config:
   kind and id for alias-add and alias-delete; names as the server lists them,
   current first; use(name) returning the rename's promise; onChange(result,
   action, name) after a write, action being use, add or delete; status(message). */
function renderAliasEditor(cell, config) {
	cell.textContent = "";

	const list = document.createElement("div");
	list.className = "cardAliasList";

	config.names.slice(1).forEach(name => list.appendChild(aliasEditorRow(name, config)));
	list.appendChild(aliasEditorOpenButton(cell, config));

	cell.appendChild(list);
}

function aliasEditorOpenButton(cell, config) {
	const open = document.createElement("button");
	open.type = "button";
	open.className = "songEditRowAdd cardAliasOpen";
	open.textContent = "+";
	open.addEventListener("click", () => {
		const row = aliasEditorAddRow(cell, config);
		open.replaceWith(row);
		row.querySelector(".cardAliasNew").focus();
	});
	return open;
}

/* Saved on blur, as every other box in the card editors is; Enter does the same
   and opens a fresh box for the next one, since the save redraws the editor closed. */
function aliasEditorAddRow(cell, config) {
	const row = document.createElement("div");
	row.className = "cardAliasRow cardAliasAddRow";

	const input = document.createElement("input");
	input.type = "text";
	input.className = "cardAliasNew";
	input.placeholder = t("alias.addPlaceholder");
	input.title = t("alias.add");

	let viaEnter = false;

	input.addEventListener("keydown", event => {
		if (event.key === "Enter") {
			event.preventDefault();
			viaEnter = true;
			input.blur();
		}
	});

	input.addEventListener("blur", () => {
		const name = input.value.trim();
		const refocus = viaEnter;
		viaEnter = false;

		if (name === "" || input.disabled) {
			return;
		}

		input.disabled = true;
		postJson(ALIAS_ADD_ENDPOINT, { kind: config.kind, id: Number(config.id), name })
			.then(result => {
				input.disabled = false;
				if (result.status === "ok") {
					config.status("");
					config.onChange(result, "add", name);

					const open = cell.querySelector(".cardAliasOpen");
					if (refocus && open) {
						open.click();
					}
					return;
				}

				/// Already one of its names: nothing to fix, so the box empties.
				if (result.status === "duplicate") {
					input.value = "";
				}
				config.status(result.message);
			})
			.catch(error => {
				input.disabled = false;
				config.status(t("status.submitFailed", { error: error.message }));
			});
	});

	row.appendChild(input);
	return row;
}

function aliasEditorRow(name, config) {
	const row = document.createElement("div");
	row.className = "cardAliasRow";

	const label = document.createElement("span");
	label.className = "cardAliasName";
	label.textContent = name;

	const use = document.createElement("button");
	use.type = "button";
	use.className = "cardAliasUse";
	use.textContent = t("alias.use");

	const remove = document.createElement("button");
	remove.type = "button";
	remove.className = "cardAliasDelete";
	remove.textContent = "×";
	remove.title = t("alias.delete");

	function busy(on) {
		row.querySelectorAll("button").forEach(button => button.disabled = on);
	}

	function settle(request, action) {
		busy(true);
		request
			.then(result => {
				busy(false);
				if (result.status === "ok") {
					config.status("");
					config.onChange(result, action, name);
				}
				else if (result.status === "listed") {
					showListed(result.message);
				}
				else {
					config.status(result.message);
				}
			})
			.catch(error => {
				busy(false);
				config.status(t("status.submitFailed", { error: error.message }));
			});
	}

	function deleteName(clearListings) {
		settle(postJson(ALIAS_DELETE_ENDPOINT, { kind: config.kind, id: Number(config.id), name, clear_listings: clearListings }), "delete");
	}

	/// Which albums list the song under this name, and the way past them, in the row itself.
	function showListed(message) {
		const old = row.querySelector(".cardAliasListed");
		if (old) {
			old.remove();
		}

		const note = document.createElement("div");
		note.className = "cardAliasListed";

		const text = document.createElement("span");
		text.textContent = message;

		const relist = document.createElement("button");
		relist.type = "button";
		relist.className = "cardAliasRelist";
		relist.textContent = t("alias.deleteAndRelist");
		relist.addEventListener("click", () => deleteName(true));

		note.append(text, relist);
		row.appendChild(note);
	}

	use.addEventListener("click", () => settle(config.use(name), "use"));

	/// A deleted song name cannot be added back from any card, so it asks first.
	remove.addEventListener("click", () => {
		if (confirm(t("alias.deleteConfirm", { name }))) {
			deleteName(false);
		}
	});

	row.append(label, use, remove);
	return row;
}
