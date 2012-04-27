$(document).ready(function() {
	var ajax_box = function() {
		var box = $("<div id='ajax_info'>Bitte warten, lade Daten… <img src='images/ajax-loader.gif'></div><div id='greyout'></div>");
		$("body").append(box);
		return box;
	}

	$("body").append($("<img src='images/ajax-loader.gif' style='position: fixed; top: 0; right: 0; margin: 10px;' alt='Lade Daten vom Server'>").
		ajaxStart(function() { $(this).show(); }).ajaxStop(function() { $(this).hide(); }).ajaxError(function() { $(this).hide(); }).hide());

	if(document.location.search == "" || document.location.search.match(/q=login/)) {
		$("#login input[type=text]:first").focus();
		$(".tomail").each(function() {
			var $this = $(this);
			var addr = $this.text().replace(" auf ", "@");
			$this.html($("<a href='#'></a>").text($this.text()));
			$this.find("a").click(function() {
				var subject = encodeURIComponent("Übungszettel");
				var body = encodeURIComponent("Hallo,\n\nich habe eine Frage zum Übungszettelservice:\n\n");
				document.location = "mailto:" + addr + "?subject=" + subject + "&body=" + body;
				return false;
			});
		});
		$("#login input[type=submit]").click(function() { $(this).closest("form").data("action", this.value); });
		$("#login").submit(function() {
			var $this = $(this);
			var has_err = false;
			$this.parent().find("span.error,span.info").remove();
			$this.parent().find("input[type=text]").each(function() {
				if(this.value == "") {
					$(this).parent().after("<span class='error'>Bitte gib " +
						(this.name == "name" ? "einen Benutzernamen" : "ein Passwort") + " ein.</span>");
					if(!has_err) $(this).focus();
					has_err = true;
				}
			});
			if(has_err) {
				if($this.data("action") == "Registrieren") {
					$this.find("div").first().prepend(
						"<span class='info'>Um Dich zu registrieren wähle bitte einen Benutzernamen und ein Passwort aus.</span>");
					$("#login input[type=submit][value=Anmelden]").remove();
				}
				return false;
			}
		});
		$("#login input[name=name]").change(function() {
			var $this = $(this);
			if($("#login input[type=submit][value=Anmelden]").length > 0) return;
			$("#login span.error").remove();
			$.get("index.php?q=login&name_check=" + encodeURIComponent(this.value), null, function(data) {
				if(data != "") {
					$this.parent().after("<span class='error'>" + data + "</span>");
				}
			});
		});
		var resolve_sug = $(".resolve_sug");
		if(resolve_sug.length > 0) {
			resolve_sug.after($("<a href='#'>").text("Rückfragen").click(function() {
				var question = prompt("Rückfrage:", "");

				if(question) {
					var sug = resolve_sug[0].href.match(/delsug=([0-9]+)/)[1];
					document.location.search = "delsug=" + sug + "&response=" + encodeURIComponent(question);
				}
				return false;
			})).after(", ");
		}
	}
	$("td:has(input[type=checkbox])").click(function(e) {
		if(e.originalTarget != this) return;
		var cb = $("input[type=checkbox]", this);
		cb.attr("checked", cb.attr("checked") ? "" : "checked");
		$(this).closest("form").data("changed", true);
	});
	$("a.confirm").each(function() {
		$(this).click(function() {
			return confirm("Bist Du sicher?");
		});
	});
	$("div.collapse").each(function() {
		var heading = $("h2", this).text();
		var $this = $(this);
		var filler = $("<a href='#'>Inhalt anzeigen</a>").insertBefore($this).click(function() {
			$this.fadeIn();
			filler.remove();
			return false;
		}).text(heading);
		filler.prepend("<br>&raquo; ");
		$this.hide();
	});
	if(document.location.search.match(/q=feeds/)) {
		$("td input[type=checkbox]").change(function() {
			$(this).closest("form").data("changed", true);
		});
		$(window).bind("beforeunload", function() {
			if($("form.feeds").data("changed")) {
				return "Du verlässt die Seite, ohne Deine Änderungen zu speichern.";
			}
		});
		$("form.feeds").submit(function() { $(this).data("changed", false); });
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
					box.remove();
					var lbox = $("<div class='dialog'></div><div id='greyout'></div>");
					lbox.appendTo(document.body);
					infoBox = $(lbox[0]);

					if(r.indexOf("Fehler") != 0) {
						infoBox.html("<div class='inner'><p>Mit Deinen Suchparametern wird folgendes gefunden:</p><ul style='font-size: x-small'>" + r + "</ul><br><br><input id='sub' type='submit' value='Speichern'> <input id='back' type='submit' value='Zurück'></div>");
					}
					else {
						infoBox.find("#status").html("<div class='inner'>" + r + "<br><br><a href='#' id='back'>Zurück</a></div>")
					}
					infoBox.find("#back").click(function() {
						lbox.remove();
						return false;
					});
					infoBox.find("#sub").click(function() {
						lbox.remove();
						mform._ok = 1;
						mform.submit();
						return false;
					});
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
		$("#kurse-uebersicht .exercise").mousedown(function() {
			var link = $(this);
			if(link.data("original-href")) return;
			link.data("original-href", link.attr("href"));
			var data_id = link.closest("tr").attr("id").match(/data-([0-9]+)/);
			if(data_id) {
				link.attr("href", "cache.php?data_id=" + data_id[1]);
				setTimeout(function() {
					var original_href = link.data("original-href")
					if(!original_href) return;
					link.data("original-href", false).attr("href", original_href);
				}, 100);
			}
		})
		$("tr.neu td:first-child + td a").click(function() {
			if($(window).data("exercise-mode")) return true;
			var match = $(this).closest("tr").find("td:last-child a")[0].toString().match(/d=([0-9]+)/);
			if(match) {
				$.get("index.php?r=" + match[1]);
				$(this).unbind("click").closest("tr").removeClass("neu");
			}
		});
		$("p.right").append(" | ").append($("<a href='#'>PDFs kombinieren</a>").toggle(function() {
			var pdf_link = $(this);
			$(window).data("exercise-mode", true);
			var exercise_info = $("<div id='exercise_info'>").html("<h4>PDFs kombinieren</h4><p>Bitte wähle die Übungen durch anklicken aus, die in eine PDF-Datei zusammengefasst werden sollen. Übungen mit der roten Markierung werden berücksichtigt. Klicke danach auf den Link unten:</p>").appendTo(document.body);
			var send_handler = function() {
				if($(".exercise.selected").length > 0) {
					var form = $("<form method='post' action='combine.php'>");
					$("body").append(form);
					form.append($("<input type='hidden' name='action'>").val($(this).text()));
					$(".exercise.selected").each(function() {
						var data_id = $(this).closest("tr").attr("id").match(/data-([0-9]+)/)[1];
						form.append($("<input type='hidden' name='d[]'>").val(data_id));
					});
					if(!$(window).data("is-submitted")) {
						$(window).data("is-submitted", true);
						if($(this).text() == "Drucken") {
							ajax_box();
						}
						setTimeout(function() {
							form.submit();
						}, 100);
					}
				}
				pdf_link.click();
				return false;
			};
			$("<a href='#'>Als PDF speichern</a>").appendTo(exercise_info).click(send_handler);
			$.get("index.php?q=main&ajax=can-print", function(can_print) {
				if(can_print) {
					exercise_info.append(" | ");
					$("<a href='#'>Drucken</a>").appendTo(exercise_info).click(send_handler);
				}
			});
			$(this).addClass("selected");
			$(".exercise").toggle(function() {
				$(this).removeClass("selected");
				return false;
			}, function() {
				$(this).addClass("selected");
				return false;
			}).addClass("selected");
			return false;
		}, function() {
			$(window).data("exercise-mode", false);
			$(this).removeClass("selected");
			$(".exercise").removeClass("selected").unbind("click");
			$("#exercise_info").remove();
			return false;
		}));
		$("a.course-disp-only").each(function() {
			var $this = $(this);
			var f = $this.attr("href").match(/f=([0-9]+)/)[1];
			var href = $this.attr("href");
			var spanReplacement = $("<span>").css({"textDecoration": "underline", "cursor": "pointer"}).text($this.text());
			$this.replaceWith(spanReplacement);
			$this = spanReplacement;

			var course_timeout;
			var has_fired = false;
			var menu = $("<ul class='hover_menu'>");
			$("<a>").attr("href", href).text("Nur diesen Kurs anzeigen").appendTo(menu).wrap("<li>");
			$("<a>").attr("href", "index.php?q=details&f=" + f).text("Informationen anzeigen").appendTo(menu).wrap("<li>");
			$("#course_links a").each(function() {
				if($(this).data("id") == f) {
					$("<a>").attr("href", $(this).attr("href")).text($(this).data("title")).appendTo(menu).wrap("<li>");
				}
			});

			var do_action = function(o) {
				menu.appendTo($this);
			}
			var undo_action = function(o) {
				menu.remove();
			}

			$this.hover(function() {
				if(course_timeout) {
					return;
				}
				course_timeout = window.setTimeout(function() {
					if(!has_fired) do_action($this);
					has_fired = true;
				}, 250);
			}, function() {
				if(course_timeout) {
					window.clearTimeout(course_timeout);
					course_timeout = null;
				}
				if(has_fired) {
					undo_action($this);
					has_fired = false;
				}
			});
		});
	}
	if(document.location.toString().match(/q=details/) && $(".can_edit").length > 0) {
		var feed_id = document.location.search.toString().match(/f=([0-9]+)/);
		if(feed_id) {
			feed_id = feed_id[1];

			var edit_course_url = $("#edit-course_url");
			if(edit_course_url.length > 0) {
				var pos = edit_course_url.position();
				$("<div><a title='Zusätzliche URLs editieren' href='#'><img src='images/application_form_edit.png'/></a></div>").css({
					'position': 'absolute',
					'left': pos.left + edit_course_url.width() - 16,
					'top': pos.top + 2
				}).appendTo(document.body).find('img').css('border', 'none').end().find("a").click(function() {
					$.get('index.php?q=details&f=' + feed_id + '&get=urls', function(urls) {
						var edit_form = $("<div class='dialog' id='course_edit_form'>Lade..</div><div id='greyout'></div>").appendTo(document.body);
						var inner_div = $("div#course_edit_form");
						inner_div.css({"height": "300px"}).css({
							left: ($(window).width() - inner_div.width()) / 2,
							top: ($(window).height() - inner_div.height()) / 2
						});
						inner_div.html('<h2>Zusätzliche URLs</h2><table><tr><th>Titel</th><th>URL</th></tr></table>');
						var table = inner_div.find("table");
						var rows = 5;
						function addRow(title, url) {
							var row = $("<tr>");
							var td_title = $("<td>").text(title).appendTo(row);
							var td_url = $("<td>").text(url).appendTo(row);

							td_title.click(function() {
								var old_title = td_title.text().replace(/ +$/, '');
								var edit = $("<input>").val(td_title.text().replace(/ $/, "")).blur(function() {
									var $this = $(this);
									if(td_url.text() == "") {
										td_title.text($this.val() + " ");
										return;
									}
									$.post("index.php?q=details&f=" + feed_id, "additional_urls=1&title=" +
									  encodeURIComponent(old_title) + "&url=", function() {
										$.post("index.php?q=details&f=" + feed_id, "additional_urls=1&title=" +
										 encodeURIComponent($this.val()) + "&url=" + encodeURIComponent(td_url.text()), function() {
											td_title.text($this.val() + " ");
										});
									});
								});
								td_title.html(edit);
								edit.focus();
							});
							td_url.click(function() {
								var edit = $("<input>").val(td_url.text()).blur(function() {
									var $this = $(this);
									if(td_title.text() == " ") {
										td_url.text($this.val() + " ");
										return;
									}
									$.post("index.php?q=details&f=" + feed_id, "additional_urls=1&title=" + 
									 encodeURIComponent(td_title.text().replace(/ $/, '')) + 
									 "&url=" + encodeURIComponent($this.val()), function() {
										td_url.text($this.val() + " ");
									});
								});
								td_url.html(edit);
								edit.focus();
							});

							row.appendTo(table);
						}
						for(title in urls) {
							rows--;
							addRow(title + " ", urls[title]);
						}
						while(rows-- > 0) {
							addRow(" ", "");
						}
						$("<button>Zurück</button>").css({"display": "block", "marginTop": "5px", "float": "right"}).
							appendTo(inner_div).click(function() { edit_form.remove(); return false; });
					});
					return false;
				});
			}

			$(".editable").each(function() {
				var $this = $(this);
				var is_url = $this.hasClass("url");
				var is_code = $this.hasClass("code");
				var is_to_be_shortened = $this.hasClass("short");
				var attr_name = this.id.match(/edit-(.+)/);
				if(!attr_name) return;
				attr_name = attr_name[1];
				$this.click(function(e) {
					if(e.target && e.target.nodeName == "A") return;
					if(typeof $this.transformed != "undefined") return;
					$this.transformed = 1;
					var value = is_url ? $this.find("a").attr("href") : $this.text();
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
							var content_text = content;
							if(is_to_be_shortened) {
								var content_text_domain = content_text.match(/^http:\/\/([^\/]+).+$/i);
							}
							if(content_text_domain) {
								content_text = content_text_domain[1];
							}
							$this.html("<a href=''></a>").find("a").attr("href", content).text(content_text);
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
					box.remove();
					var lbox = $("<div class='dialog'></div><div id='greyout'></div>");
					lbox.appendTo(document.body);
					infoBox = $(lbox[0]);

					if(r.indexOf("Fehler") != 0) {
						infoBox.html("<div class='inner'><p>Mit Deinen Suchparametern wird folgendes gefunden:</p><ul style='font-size: x-small'>" + r + "</ul><br><br><input id='back' type='submit' value='Zurück'></div>");
					}
					else {
						infoBox.find("#status").html("<div class='inner'>" + r + "<br><br><a href='#' id='back'>Zurück</a></div>")
					}
					infoBox.find("#back").click(function() {
						lbox.remove();
						return false;
					});
				});
				return false;
			});
			$(".buttons.small").css("position", "absolute").css("marginLeft", $(".bes-left").width() + $(".bes-right").width() - $(".buttons.small").width());
		}
	}
	if(document.location.search.match(/q=acc/)) {
		var op = $("input[name=old_pass]");
		if(op.attr("type") != "hidden") {
			$("input").attr("disabled", "disabled");
			op.removeAttr("disabled").keypress(function() {
				$(this).unbind('keypress');
				$("input").removeAttr("disabled");
			}).focus();
		}
	}
});
