var factolex_terms = {}, factolex_lexicon = {};

jQuery(document).ready( function($) {
	function factolex_remove_term() {
		var li = $(this).parent();
		var term_id = li.attr("factolexid");
		
		$.ajax({
			data: "action=factolex-remove-term&term=" + term_id + "&post=" + $("#post_ID").val(),
			type: "POST",
			url: "admin-ajax.php?action=factolex-remove-term"
		});
		factolex_lexicon[term_id] = null;
		li.remove();
		return false;
	}
	
	$("#factolex-terms-in-glossary .remove").click(factolex_remove_term);
	$("#factolex-terms-in-glossary .remove").each(function() {
		var term_id = $(this).parent().attr("factolexid");
		factolex_lexicon[term_id] = true;
	});
	$("#factolex-check-button").click( function(e) {
		$("#factolex-check-button").attr("disabled", "disabled").val(factolexL10N.loadingTerms);
		
		var div = $("#factolex-term-preview");
		if (div.length == 0) {
			div = $(document.createElement("div"))
				.attr("id", "factolex-term-preview")
				.click(function() {
					$("#factolex-choose-term").hide();
				})
				.appendTo($("body"));
		}
		
		var c = $("#content");
		if (c.css("display") == "none") {
			c = $("#content_parent");
			switchEditors.go('content');
			switchEditors.go('content');
		}
		var p = c.position();
		div
			.css({"left": p.left, "top": p.top})
			.height(c.height() - 14) // 2 * 7px border
			.width(c.width() - 14); // 2 * 7px border
		
		div.text(factolexL10N.loadingTerms).show();
		
		$.ajax({
			data: "action=factolex-checktext&text=" + encodeURIComponent($("#content").val()),
			type: "POST",
			dataType: "json",
			url: "admin-ajax.php?action=factolex-checktext",
			success: function(r) {
				$("#factolex-check-button").removeAttr("disabled").val("Check for terms");
				
				if (r.words.length == 0) {
					div.html('<p class="explanation">' + factolexL10N.errorLoading.replace("&gt;", ">").replace("&lt;", "<").replace("%tryagain", '"" id="factolex-try-again"').replace("%close", '"" id="factolex-close-term-preview"') + '</p>');
					
					$("#factolex-close-term-preview").click(function() {
						$("#factolex-term-preview").click().hide();
						return false;
					});
					
					$("#factolex-try-again").click(function() {
						$("#factolex-check-button").click();
						return false;
					});
					
					return false;
				}
				
				div.html('<p class="explanation">' + factolexL10N.explanation.replace(/&gt;/g, ">").replace(/&lt;/g, "<").replace("%close", '"" id="factolex-close-term-preview"') + '</p>' + r.html);
				
				$("#factolex-close-term-preview").click(function() {
					$("#factolex-term-preview").click().hide();
					return false;
				});
				
				factolex_terms = r.words;
					
				var choose_div = $("#factolex-choose-term");
				if (choose_div.length == 0) choose_div = $(document.createElement("div"))
					.attr("id", "factolex-choose-term")
					.appendTo(div);
				
				$(".factolex-term").click(function(e) {
					e.stopPropagation();
					var word = $(this).text();
					var term, terms = factolex_terms[word];
					choose_div.html("");
					
					var d, titles, additional_text;
					for (var i = 0, l = terms.length; i < l; i++) {
						term = terms[i];
						factolex_terms[term.id] = term;
						titles = [term.title];
						additional_text = [""];
						if (word != term.title) {
							titles.push(word);
							additional_text.push(" (" + factolexL10N.alternateSpelling + ")");
						}
						if (term.synonym_for) {
							titles.push(term.synonym_for);
							additional_text.push(" (" + factolexL10N.originalSpelling + ")");
						}
						for (var j = 0; j < titles.length; j++) {
							d = $(document.createElement("div"))
								.html(titles[j] + " <span class=\"tags\">" + factolexL10N.tags + " <i>" + term.tags + "</i></span> <span class=\"add\">" + factolexL10N.add + additional_text[j] + "</span><br/><span class=\"fact\">" + term.fact + "</span>")
								.attr({"factolexid": term.id, "factolextitle": titles[j]})
								.appendTo(choose_div)
								.click(function() {
									var term = factolex_terms[$(this).attr("factolexid")];
									if (factolex_lexicon[term.id]) {
										// alert("already in your lexicon");
										return true;
									}
									var title = $(this).attr("factolextitle");
									
									$.ajax({
										data: "action=factolex-add-term&term=" + term.id + "&post=" + $("#post_ID").val() + "&title=" + encodeURIComponent(title) + "&tags=" + encodeURIComponent(term.tags) + "&link=" + encodeURIComponent(term.link) + "&fact_id=" + encodeURIComponent(term.fact_id) + "&fact_title=" + encodeURIComponent(term.fact) + "&fact_type=" + encodeURIComponent(term.fact_type) + "&fact_source=" + encodeURIComponent(term.fact_source),
										type: "POST",
										url: "admin-ajax.php?action=factolex-add-term"
									});
									
									factolex_lexicon[term.id] = term;
									var li = $(document.createElement("li")).attr("factolexid", term.id).appendTo($("#factolex-terms-in-glossary"));
									$(document.createElement("a")).text(title).addClass("term").attr({"href": term.link, "title": term.fact}).appendTo(li);
									$(document.createTextNode(" ")).appendTo(li);
									$(document.createElement("a")).text("Remove").addClass("remove").attr("href", "").appendTo(li).click(factolex_remove_term);
									$(document.createElement("br")).appendTo(li);
									$(document.createElement("span")).text(term.fact).addClass("fact").appendTo(li);
								});
							if (i == 0 && j == 0) {
								d.addClass("first");
							}
						}
					}
					var p = $(this).position();
					choose_div.css({"top": p.top + $(this).height()}).show();
				});
			}
		});
		return false;
	});
});
