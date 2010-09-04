$(document).ready(function() {
	var ajax_box = function() {
		var box = $("<div id='ajax_info'>Bitte warten, lade Daten… <img src='ajax-loader.gif'></div><div id='greyout'></div>");
		$("body").append(box);
		return box;
	}

	$("body").append($("<img src='ajax-loader.gif' style='position: fixed; top: 0; right: 0; margin: 10px;' alt='Lade Daten vom Server'>").
		ajaxStart(function() { $(this).show(); }).ajaxStop(function() { $(this).hide(); }).ajaxError(function() { $(this).hide(); }).hide());

	if(document.location.search == "") {
		$("#login input:first").focus();
	}
	$("td:has(input[type=checkbox])").click(function(e) {
		if(e.originalTarget != this) return;
		var cb = $("input[type=checkbox]", this);
		cb.attr("checked", cb.attr("checked") ? "" : "checked");
	});
	$("a.confirm").each(function() {
		$(this).click(function() {
			return confirm("Bist Du sicher?");
		});
	});
	$("div.collapse").each(function() {
		var heading = $("h2", this).text();
		var $this = $(this);
		var filler = $("<br>&raquo; <a href=''></a>").text(heading).click(function() {
			$this.fadeIn();
			filler.remove();
			return false;
		});
		$this.hide().before(filler);
	});
	if(document.location.search.match(/q=feeds/)) {
		$("form.newcourse").each(function() {
			$(this).submit(function() {
				var mform = this;
				if(typeof(this._ok) != "undefined") return;

				var query = "";
				var inputs = $("input[name], textarea[name]", this);
				for(var input = 0; input < inputs.length; input++) {
					if(inputs[input].value.replace(/^\s+|\s+$/g, '') == "") {
						alert("Bitte fülle alle Felder aus.");
						return false;
					}
					query += inputs[input].name + "=" + encodeURIComponent(inputs[input].value) + "&";
				}

				var box = ajax_box().ajaxError(function() {
					$(this).remove();
					alert("Deine Suche produziert einen Fehler und kann daher nicht ausgewertet werden.");
				});
				$.post("index.php?q=feeds", query + "preview=1", function(r) {
					var mwnd = window.open("", "Vorschau", "width=350,height=600");
					mwnd.document.write("<style> * { font-family: sans-serif; }</style><h1>Vorschau</h1>");
					if(r.indexOf("Fehler") != 0) {
						mwnd.document.write("<p>Mit Deinen Suchparametern wird folgendes gefunden:</p>");
						mwnd.document.write("<ul style='font-size: x-small'>" + r + "</ul>");
						mwnd.document.write("<br><br><input id='sub' type='submit' value='Speichern'>");
						mwnd.document.getElementById("sub").onclick = function() {
							mform._ok = 1;
							mform.submit();
							mwnd.close();
						}
					}
					else {
						mwnd.document.write(r);
						mwnd.document.write("<br><br><a href='#' onclick='window.close();'>Zurück</a>");
					}
					mwnd.document.close();
					box.remove();
				});

				return false;
			}).find("input[type=submit]").val("Vorschau");
		});
	}
	if($(".editable-note").length > 0) {
		$(".editable-note").each(function() {
			var $this = $(this);
			var note_id = this.id.match(/edit-(.+)/);
			if(!note_id) return;
			note_id = note_id[1];
			$this.click(function() {
				if(typeof $this.transformed != "undefined") return;
				$this.transformed = 1;
				var value = $this.text();
				$this.html("<input type='text' style='width: 90%'>").find("input, textarea").val(value).blur(function() {
					var content = $this.find("input, textarea").val().replace(/^\s+|\s+$/g, '');
					$this.text(content);

					if(value != content) {
						$.post("index.php", "note_id=" + encodeURIComponent(note_id) + "&value=" +
							encodeURIComponent(content), function(a) {
								if(a != "") $this.html(a);
								$this.transformed = undefined;
							}
						);
					}
					else {
						$this.transformed = undefined;
					}
				}).keypress(function(e) {
					if(e.keyCode == 13) $this.find("input").trigger("blur");
				}).focus();
			});
		});
		$("a.erledigt").each(function() {
			$(this).click(function() {
				$.get("index.php?ajax=1&d=" + encodeURIComponent(this.href.match(/d=([0-9]+)/)[1]));
				if(document.location.search.match(/inv=1/)) {
					if($(this).text() == "Erledigt") {
						$(this).text("Unerledigt");
					}
					else {
						$(this).text("Erledigt");
					}
				}
				else {
					$(this).closest("tr").remove();
				}
				return false;
			});
		});
		$("tr.neu td:first-child + td a").click(function() {
			var match = $(this).closest("tr").find("td:last-child a")[0].toString().match(/d=([0-9]+)/);
			if(match) {
				$.get("index.php?r=" + match[1]);
				$(this).unbind("click").closest("tr").removeClass("neu");
			}
		});
		$("p.right").append(" | ").append($("<a href='#'>PDFs kombinieren</a>").toggle(function() {
			$(this).addClass("selected");
			$(".exercise").toggle(function() {
				$(this).removeClass("selected");
			}, function() {
				$(this).addClass("selected");
			}).addClass("selected");
			return false;
		}, function() {
			$(this).removeClass("selected");
			if($(".exercise.selected").length > 0) {
				var form = $("<form method='post' action='combine.php'>");
				$("body").append(form);
				$(".exercise.selected").each(function() {
					form.append($("<input type='hidden' name='d[]'>").val(this.href));
				});
				form.submit();
			}
			$(".exercise").removeClass("selected").unbind('toggle');
			return false;
		}));
	}
	if(document.location.toString().match(/q=details/) && $(".can_edit").length > 0) {
		var feed_id = document.location.search.toString().match(/f=([0-9]+)/);
		if(feed_id) {
			feed_id = feed_id[1];
			$(".editable").each(function() {
				var $this = $(this);
				var is_url = $this.hasClass("url");
				var is_code = $this.hasClass("code");
				var attr_name = this.id.match(/edit-(.+)/);
				if(!attr_name) return;
				attr_name = attr_name[1];
				$this.click(function() {
					if(typeof $this.transformed != "undefined") return;
					$this.transformed = 1;
					var value = $this.text();
					if(is_code) {
						$this.html("<textarea style='width: 90%; height: 160px;'></textarea>");
					}
					else {
						$this.html("<input type='text' style='width: 90%'>");
					}
					$this.find("input, textarea").val(value).blur(function() {
						var content = "";
						if(is_url) {
							content = $this.find("input, textarea").val().replace(/^\s+|\s+$/g, '');
							if(content == "") return;
							$this.html("<a href=''></a>").find("a").attr("href", content).text(content);
						}
						else if(is_code) {
							content = $this.find("input, textarea").val().replace(/^\s+|\s+$/g, '');
							if(content == "") return;
							$this.html("<code></code>").find("code").html(content.replace(/</g, "&lt;").replace(/\n/g, "<br />\n"));
						}
						else {
							content = $this.find("input, textarea").val().replace(/^\s+|\s+$/g, '');
							if(content == "") return;
							$this.text(content);
						}
						$.post("index.php?q=details&f=" + feed_id, "property=" + encodeURIComponent(attr_name) + "&value=" +
							encodeURIComponent(content), function(a) {
								if(a != "") $this.html(a);
								$this.transformed = undefined;
							}
						);
					}).keypress(function(e) {
						if(is_code) return;
						if(e.keyCode == 13) $this.find("input").trigger("blur");
					}).focus();
				});
			});
			$(".bes-right").append("<br><br><input type='button' value='Vorschau' class='' />").find("input[type=button]").click(function() {
				var query = "";
				var edits = $(".editable");
				for(var edit = 0; edit < edits.length; edit++) {
					var attr_name = edits[edit].id.match(/edit-(.+)/);
					if(!attr_name) continue;
					query += encodeURIComponent(attr_name[1]) + "=" + encodeURIComponent($(edits[edit]).text()) + "&";
				}

				var box = ajax_box().ajaxError(function() {
					alert("Deine Suche produziert einen Fehler und kann daher nicht ausgewertet werden.");
					$(this).remove();
				});
				$.post("index.php?q=feeds", query + "preview=1", function(r) {
					var mwnd = window.open("", "Vorschau", "width=350,height=600");
					mwnd.document.write("<style> * { font-family: sans-serif; }</style><h1>Vorschau</h1>");
					if(r.indexOf("Fehler") != 0) {
						mwnd.document.write("<p>Mit Deinen Suchparametern wird folgendes gefunden:</p>");
						mwnd.document.write("<ul style='font-size: x-small'>" + r + "</ul>");
					}
					else {
						mwnd.document.write(r);
					}
					mwnd.document.write("<br><br><a href='#' onclick='window.close();'>Zurück</a>");
					mwnd.document.close();
					box.remove();
				});
				return false;
			});
		}
	}
	if(document.location.search.match(/q=acc/)) {
		$("input").attr("disabled", "disabled");
		$("input[name=old_pass]").removeAttr("disabled").keypress(function() {
			$(this).unbind('keypress');
			$("input").removeAttr("disabled");
		}).focus();
	}
});
