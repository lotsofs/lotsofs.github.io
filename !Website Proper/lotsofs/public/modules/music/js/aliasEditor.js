const ALIAS_DELETE_ENDPOINT = "/music/ajax/alias-delete";

/* A card's other names in edit mode, each with a button to make it the current
   name and one to delete it. Shared by the song card (songs.js) and the album
   and artist cards (albumCard.js), each of which says how to make a name current
   and what to do with the answer. config: kind and id for alias-delete; names as
   the server lists them, current first; use(name) returning the rename's promise;
   onChange(result, action, name) after a write; status(message). */
function renderAliasEditor(cell, config) {
	cell.textContent = "";

	const list = document.createElement("div");
	list.className = "cardAliasList";

	config.names.slice(1).forEach(name => list.appendChild(aliasEditorRow(name, config)));

	cell.appendChild(list);
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
