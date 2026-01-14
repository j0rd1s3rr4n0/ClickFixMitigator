(function() {
	chrome.runtime.onMessage.addListener(function (request, sender, response) {
		if(request.method.toLowerCase() === "shortcode-builder") {
			shortcode_builder();
		}
		response();
		return true;
	});

	function shortcode_builder() {
		if(document.body.classList.contains("_cf_sc_builder_open")) {
			shortcodeBuilderClose();
			return;
		}
		document.body.classList.add("_cf_sc_builder_open");

		if(document.body.dataset._cf_initialized) return;
		document.body.dataset._cf_initialized = "true";

		const builder = chrome.runtime.getURL("page/builder.html");

		// console.log(builder);

		fetchGet(builder, data => {
			let $wrapper = document.createElement("div");
			$wrapper.innerHTML = data;
			$wrapper.id = "_cf_builder_wrapper";
			document.body.insertBefore($wrapper, document.body.childNodes[0]);
			initShortcodeBuilder($wrapper);
		}, function () {
			console.log("Failed");
		});
	}

	function shortcodeBuilderClose() {
		document.body.classList.remove("_cf_sc_builder_open");
	}

	async function initShortcodeBuilder(root) {
		const builder_actions = await fetch(chrome.runtime.getURL("page/shortcodes.json"))
			.then(res => res.json());

		// console.log(builder_actions);

		let featureButtons = root.querySelectorAll("._cf_sc_feature_page_btn");
		let actionButtons = root.querySelectorAll("._cf_sc_button_action");

		let featureButtonsPage = root.querySelector("._cf_sc_feature_buttons_page");
		let featurePages = root.querySelectorAll("._cf_sc_feature_page");

		let selectingBlock = root.querySelector("._cf_sc_selecting_block");
		let actions_block = root.querySelector("._cf_sc_actions_block");
		let fieldsBlock = root.querySelector("._cf_sc_shortcode_fields");
		let builder = root.querySelector("._cf_sc_builder_fields");
		let selectDoneBtn = root.querySelector("._cf_sc_selection_done_btn");

		let cancel_btn = root.querySelector("._cf_sc_selection_done_wrapper ._cf_builder_cancel_btn");
		let done_btn = root.querySelector("._cf_builder_done_btn");

		let back_btn = root.querySelector("._cf_sc_back_wrapper ._cf_builder_btn");

		let notepad = root.querySelector("#shortcodes-notepad");
		let copyBtn = document.querySelector('._cfb_copy_btn');

		let picking_indicator = document.querySelector("._cf_sc_picking_indicator");

		copyBtn.addEventListener("click", (e) => {
			e.preventDefault();
		});

		let clipboard = new ClipboardJS('._cfb_copy_btn', {
			text: function(trigger) {
				return notepad.innerText;
			}
		});
		clipboard.on('success', function(e) {
			console.log("Copied to clipboard");
			e.clearSelection();
		});

		clipboard.on('error', function(e) {
			console.log("Can't copy", e);
		});

		selectDoneBtn.addEventListener("click", doneSelection, false);

		featureButtons.forEach(btn => {
			btn.addEventListener("click", showBuilderFeature);
		});

		actionButtons.forEach(btn => {
			btn.addEventListener("click", startAction);
		});


		cancel_btn.addEventListener("click", cancelAction);
		done_btn.addEventListener("click", doneAction);
		back_btn.addEventListener("click", showFeatureButtonsPage);

		function showBuilderFeature(e) {
			e.preventDefault();
			back_btn.classList.remove("_cf_hidden");

			let feature = e.currentTarget.dataset.pageLink;
			show_feature(feature);
		}

		function showFeatureButtonsPage() {
			hideAllFeaturePages();
			featureButtonsPage.classList.remove("_cf_hidden");
			back_btn.classList.add("_cf_hidden");
		}

		function show_feature(feature) {
			hideFeatureButtonsPage();
			hideAllFeaturePages();
			let page = document.querySelector(`._cf_sc_feature_page[data-page='${feature}']`);
			page.classList.remove("_cf_hidden");
		}

		function hideFeatureButtonsPage() {
			featureButtonsPage.classList.add("_cf_hidden");
		}

		function hideAllFeaturePages() {
			featurePages.forEach(page => {
				page.classList.add("_cf_hidden");
			});
		}

		let action = null;
		let last_action_name = null;
		function startAction(e) {
			console.log("Start action");
			reset();
			// selectingBlock.classList.remove("_cf_hidden");
			e.preventDefault();
			action = builder_actions[e.currentTarget.dataset.action];
			last_action_name = e.currentTarget.dataset.action;

			buildFields(action);
			let firstParam = action.params[0];
			if(firstParam.type === 'ui_element') {
				fieldsBlock.querySelector("._cf_builder_pick_btn").click();
			}
		}

		function doneAction() {
			let params = [];

			for(let i in action.params) {
				if(!action.params.hasOwnProperty(i)) continue;
				let param = action.params[i];
				let paramEl = fieldsBlock.elements[`param[${i}]`];
				if(!paramEl.tagName && paramEl.length) paramEl = paramEl[0];
				let builtParam = buildParam(param, i, paramEl);

				if(builtParam.skip) continue;

				if(!builtParam.success) {
					paramEl.parentNode.classList.add("_cf_field_error")

					if(paramEl.emptyFieldList) {
						paramEl.emptyFieldList.forEach(el => {
							el.parentNode.classList.add("_cf_field_error")
						})
					}
					return;
				} else
					params.push(builtParam.value);
			}

			let shortcode = `[${action.name} ${params.join(" ").trim()}]`;
			let shortcodeDiv = document.createElement("div");
			shortcodeDiv.innerHTML = shortcode;
			notepad.insertBefore(shortcodeDiv, null);
			cancel_btn.click();
		}

		function cancelAction(e) {
			e.preventDefault();
			actions_block.classList.remove("_cf_hidden");
			builder.classList.add("_cf_hidden");
			fieldsBlock.innerHTML = "";
			picking_indicator.classList.remove("show");
			picking = false;
			reset();
		}

		function buildParam(param, i, el) {
			let paramValueStr = "";
			if(param.type === "list") {
				let paramValueIndex = el.selectedIndex;

				if(!param.required) {
					if (paramValueIndex > 0) paramValueIndex -= 1;
					else return {
						success: true,
						skip: true
					}
				}

				let paramValue = param.list[paramValueIndex];
				paramValueStr = param.name || paramValue.name;

				let emptyParamEls = [];

				if(paramValue.type === "input") {
					let paramValueEl = fieldsBlock.elements[`param[${i}]`][1];
					let value = paramValueEl.value;
					paramValueStr = paramValueStr.replace(`{${0}}`, value);

					if(!value || !value.trim()) emptyParamEls.push(value);
				} else if(paramValue.type === "list_multiselect") {
					let paramValueEl = fieldsBlock.elements[`param[${i}]`][1];
					let value = Array.from(paramValueEl.selectedOptions).map(item => item.value).join(",");
					paramValueStr = paramValueStr.replace(`{0}`, value);
					if(!value || !value.trim()) emptyParamEls.push(value);
				} else {
					let value = paramValue.value;
					if(/\{\d}/.test(paramValueStr)) {
						paramValueStr = paramValueStr ? paramValueStr.replace(`{0}`, value) : value;
					}
				}

				if(emptyParamEls.length) {
					return {
						success: false,
						emptyFieldList: emptyParamEls
					}
				}
/*
				let paramName = param.name || "";
				if(paramName) {
					if(paramName.includes("{0}")) {
						paramValueStr = paramName.replace("{0}", paramValueStr);
					} else {
						paramValueStr = paramName+"=\""+paramValueStr+"\"";
					}
				}*/
			} else if(param.type === "ui_element" || param.type === "string") {
				let name = param.name;
				if(name === "$_var") {
					name = document.querySelector(`[name='${el.name+"_var"}']`).value+"={0}";
				}
				if(param.required) {
					if(!el.value.trim()) {
						return {
							success: false,
							message: `${name || "First"} field is required.`
						}
					}
				} else {
					if(!el.value.trim()) return {
						success: true,
						skip: true
					};
				}
				let value;
				if(name) {
					value = name.replace("{0}", el.value);
				} else {
					value = el.value;
				}
				paramValueStr = value;
			}

			return {
				success: true,
				value: paramValueStr
			}
		}

		function buildFields(action) {
			actions_block.classList.add("_cf_hidden");
			builder.classList.remove("_cf_hidden");
			fieldsBlock.innerHTML = "";

			action.params.forEach((param,i) => {
				fieldsBlock.appendChild(buildField(param,i));
			});
			jQuery("._sc_builder_select:not(.select2-hidden-accessible)").select2({
				dropdownCssClass: "_sc_builder_select2"
			});
		}

		function buildField(param, i) {
			let el;

			switch(param.type) {
				case 'ui_element':
					el = buildUIElement(param, i);
					break;
				case 'list':
					el = buildList(param, i);
					break;
				case 'string':
					el = buildStringField(param, i);
			}

			return el;
		}

		let picking = false;
		let pick_input = null;

		function buildUIElement(param, i) {
			let label = document.createElement("label");
			label.innerText = param.description;

			let input = document.createElement("input");
			input.name = `param[${i}]`;

			let addon_btn = document.createElement("a");
			addon_btn.href = "javascript:void(0)";
			addon_btn.innerText = "Pick";
			addon_btn.className = "_cf_builder_btn _cf_builder_pick_btn";

			let wrapper = document.createElement('div');
			wrapper.appendChild(label);
			wrapper.appendChild(input);
			wrapper.appendChild(addon_btn);
			wrapper.className = "_cf_builder_elem_wrapper";

			addon_btn.addEventListener("click", e => {
				if(picking) {
					doneSelection();
					addon_btn.innerText = "Pick";
				}
				else {
					pickElements(param, input);
					addon_btn.innerText = "Done picking";
				}
				picking = !picking;
			});

			return wrapper;
		}

		function buildStringField(param, i) {

			let var_label, var_input;
			if(param.name === "$_var") {
				var_label = document.createElement("label");
				var_label.innerText = "Variable name";

				var_input = document.createElement("input");
				var_input.name = `param[${i}]_var`;
				var_input.value = "$";
			}

			let label = document.createElement("label");
			label.innerText = param.description;

			let input = document.createElement("input");
			input.name = `param[${i}]`;

			let wrapper = document.createElement('div');
			if(var_input && var_label) {
				wrapper.appendChild(var_label);
				wrapper.appendChild(var_input);
			}
			wrapper.appendChild(label);
			wrapper.appendChild(input);
			wrapper.className = "_cf_builder_elem_wrapper";

			return wrapper;
		}

		function pickElements(param, input) {
			picking_indicator.classList.add("show");
			picking_indicator.innerHTML = param.description;
			pick_input = input;
			let $els = document.querySelectorAll(param.selector);
			$els.forEach($el => {
				if($el.closest("#_cf_builder")) return;
				highlightEls($el, findAttribute($el, param.field), toggleSelection, param)
			});
		}

		function buildList(param, i) {
			let wrapper = document.createElement("div");
			let paramValueContainer = document.createElement("div");

			let selectBoxWrapper = document.createElement("div");
			let paramSelectBox = document.createElement("select");
			paramSelectBox.name = `param[${i}]`;
			paramSelectBox.className = "_sc_builder_select";

			let title = document.createElement("label");
			title.innerText = param.description;

			selectBoxWrapper.appendChild(title);
			selectBoxWrapper.appendChild(paramSelectBox);

			wrapper.appendChild(selectBoxWrapper);
			wrapper.appendChild(paramValueContainer);

			let list = Array.from(param.list);

			if(!param.required)
				list.unshift({title: "Default", empty: true})

			list.forEach((e,i) => {
				let option = document.createElement("option");
				option.value = i.toString();
				option.text = e.title;
				paramSelectBox.appendChild(option);
			});

			jQuery(paramSelectBox).change(e => {
				paramValueContainer.innerHTML = "";
				if(list[paramSelectBox.selectedIndex].type) {
					let paramValueList = buildListParamValueBox(list[paramSelectBox.selectedIndex], i);
					paramValueList.forEach(value => {
						paramValueContainer.appendChild(value);

						if(value.tagName.toLowerCase() === "select")
							jQuery(value).select2({
								dropdownCssClass: "_sc_builder_select2"
							});
					});
				}
			});

			jQuery(paramSelectBox).change();

			return wrapper;
		}

		function buildListParamValueBox(param, i) {
			let list = [];

			let box;
			if(param.type === "input") {
				box = document.createElement("input");
				box.name = `param[${i}]`;
				box.placeholder = "Value for the above parameter";
			} else if(param.type === "list") {
				box = buildValueSelect(param.list, false, i);
			} else if(param.type === "list_multiselect") {
				box = buildValueSelect(param.list, true, i);
			}

			list.push(box);
			/*
			param.values.forEach((parameter,i2) => {
				if(Array.isArray(parameter)) {
					box = buildValueSelect(parameter, false, i, i2);
				} else if(typeof parameter === 'object') {
					box = buildValueSelect(parameter.list, !!parameter.multiple, i, i2);
				} else {
					box = document.createElement("input");
					box.name = `param[${i}][${i2}]`;
					box.placeholder = "Value for the above parameter";
					// TODO validate by param
				}

			});*/

			return list;
		}

		function buildValueSelect(list, multiple=false, i) {
			let box = document.createElement("select");
			box.name = `param[${i}]`;
			box.multiple = multiple;
			box.className = "_sc_builder_select";
			list.forEach(el => {
				let option = document.createElement("option");
				option.value = el.toString();
				option.text = el.toString();
				box.appendChild(option);
			});

			return box;
		}

		let _selection = {};
		function toggleSelection(param, e) {
			let el = e.currentTarget;
			let val = el.getAttribute("data-clipboard-text");
			if(param.field.toLowerCase() === 'id') val = '#'+val;
			let id;
			if(param.include_parent !== false) {
				let parent = this.parentNode && this.parentNode.closest("[opt-type='block-v3']");
				id = parent ? parent.id : '_default';
			} else {
				id = '_default';
			}

			if(!_selection[id]) _selection[id] = [];

			if(!(val && val !== "null" && val !== "undefined")) return;

			if(el.classList.contains("selected")) {
				_selection[id].splice(_selection[id].indexOf(val),1);
			} else {
				_selection[id].push(val);
			}
			el.classList.toggle("selected");
		}

		function doneSelection() {
			reset();

			let list = [];

			let keys = Object.keys(_selection);

			if(keys.length > 1) {
				keys.forEach(key => {
					list.push(..._selection[key]);
				});
			} else {
				keys.forEach(key => {
					if (key === "_default") {
						list.push(_selection[key].join(","));
					} else {
						list.push(_selection[key].join(",") + " of #" + key);
					}
				});
			}

			pick_input.value = list.join(",");
			pick_input = null;
			_selection = {};
			picking_indicator.innerHTML = "";
			picking_indicator.classList.remove("show");
		}
	}

	function highlightEls($el, txt, listener, param) {
		const scrollTop = document.querySelector("html").scrollTop || window.scrollTop || document.body.scrollTop;
		const scrollLeft = document.querySelector("html").scrollLeft || window.scrollLeft || document.body.scrollLeft;
		const rect = $el.getBoundingClientRect();
		const _id = $el.getAttribute("data-klikfx-uniq-id") || makeId(10);
		let overlay = document.querySelector("[data-klikfx-overlay='" + _id + "']");
		if(!overlay) {
			overlay = document.createElement("div");
			overlay.setAttribute("data-klikfx-overlay", _id);

			overlay.style.top = (scrollTop + rect.top) + "px";
			overlay.style.left = (scrollLeft + rect.left) + "px";
			if($el.getAttribute("type") && ($el.getAttribute("type").toLowerCase()==="checkbox" || $el.getAttribute("type").toLowerCase()==="radio" || $el.getAttribute("type").toLowerCase()==="range")) {
				overlay.style.minWidth = rect.width+"px";
				overlay.style.minHeight = rect.height+"px";
			} else {
				overlay.style.height = rect.height + "px";
				overlay.style.width = rect.width + "px";
			}

			if(!rect.height || !rect.width) {
				overlay.style.display = "none";
			}

			document.body.appendChild(overlay);
			const br = document.createElement("br");
			br.style.display = "none";
			document.body.appendChild(br);
			$el.setAttribute("data-klikfx-uniq-id", _id);

			overlay.setAttribute("data-clipboard-text", txt);
			if(txt) new ClipboardJS(overlay);
		}
		overlay.textContent = txt || "[Empty]";

		if(typeof listener === 'function') {
			overlay.addEventListener("click", listener.bind($el, param), false);
		}
	}

	function hideKey($el) {
		const id = $el.getAttribute("data-klikfx-uniq-id");
		if(id) {
			overlay = document.querySelector("[data-klikfx-overlay='"+id+"']");
			overlay.nextSibling.remove();
			overlay.remove();
			$el.removeAttribute("data-klikfx-uniq-id");
		}
	}

	function findAttribute($el, key) {
		if($el.classList.contains("select-dropdown") && $el.tagName.toLowerCase() === "input") {
			const select = $el.parentNode.querySelector("select");
			return select && select.getAttribute(key);
		}

		let quantityRegExp = /^quantity\[["'](.+?)["']]$/i;

		if(key.toLowerCase() === "id" && quantityRegExp.test($el.getAttribute("name"))) {
			let match = $el.getAttribute("name").match(quantityRegExp);
			return match[1];
		} else if(key.toLowerCase() === "name" && quantityRegExp.test($el.getAttribute("name"))) {
			let match = $el.getAttribute("name").match(quantityRegExp);
			return match[1];
		}


		return $el.getAttribute(key);
	}

	function makeId(length) {
		let text = "";
		const possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

		for (let i = 0; i < length; i++)
			text += possible.charAt(Math.floor(Math.random() * possible.length));

		return text+(new Date()).getTime();
	}


	function fetchGet(url, success, fail) {
		return _fetch("GET", url, undefined, success, fail);
	}

	function fetchPost(url, data, success, fail) {
		return _fetch("POST", url, data, success, fail);
	}

	function _fetch(method, url, data, success, fail) {
		const xhr = new XMLHttpRequest();
		xhr.addEventListener("readystatechange", function () {
			if(xhr.readyState === XMLHttpRequest.DONE) {
				if(xhr.status === 200) success(xhr.responseText);
				else fail(xhr.responseText);
			}
		});
		xhr.open(method, url, true);
		xhr.setRequestHeader("Content-Type", "application/json");
		xhr.setRequestHeader("Accept", "application/json");
		xhr.send(JSON.stringify(data));
	}

	function reset() {
		const $els = document.querySelectorAll("[data-klikfx-uniq-id]");
		for(let i = 0; i < $els.length; i++) {
			const $el = $els[i];
			hideKey($el);
		}
	}
})();

//@ sourceURL=sc-builder.js