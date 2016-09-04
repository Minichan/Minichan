require("../style/layout.css");

import 'babel-polyfill';
import Fingerprint from './fingerprint.js'
import $ from 'jquery';
window.$ = window.jQuery = $;
require('./extras.js');

hljs.initHighlightingOnLoad();

window.massDelete = massDelete;
function massDelete(domain, topic_id) {

	var output = [];

	output[0] = "id=" + topic_id;
	output[1] = "CSRF_token=" + $("#CSRF_token").val();

	$(".mass_delete:checked").each(function(i){
		output[i+2] = "reply_id[]=" + $(this).val();
	});

	if(!confirm("Are you sure you want to delete " + (output.length-2) + " replies?")) return;

	output = output.join("&");

	window.location = domain + "action.php?action=mass_delete&" + output;
}

window.uploadImage = uploadImage;
function uploadImage(file) {
	/* Is the file an image? */
	if (!file || !file.type.match(/image.*/)) return;

	/* It is! */
	document.getElementById("uploader").innerHTML = "Uploading...";
	document.getElementById("uploader").href = "";
	var reader = new FileReader();
	reader.onloadend = function () {
		console.log('Doin');
		$.ajax({
			url: 'https://api.imgur.com/3/image',
			type: 'post',
			headers: {
				Authorization: 'Client-ID ' + IMGUR_CLIENT_ID
			},
			data: {
				image: reader.result.split(',')[1]
			},
			dataType: 'json',
			success: function(response) {
				if(!response.success) {
					alert(JSON.stringify(response));
					return;
				}

				document.getElementById("imageurl").value = response.data.link;
				$("#uploader").remove();
			}
		});
	}
	reader.readAsDataURL(file);
}

$(document).ready(function(){
	if(typeof(FormData) == "undefined") {
		$("#uploader").remove();
	}

	$(".mass_delete").change(function(){
		if($(".mass_delete:checked").length > 0){
			$(".do_massDelete").css("display", "block");
		}else{
			$(".do_massDelete").css("display", "none");
		}

		$("#massDeleteCount").text($(".mass_delete:checked").length);
	});

	var $div = null;
	var hovering = false;
	var divHovering = false;

	function scheduleTimeout() {
		setTimeout(function() {
			if($div != null && !hovering && !divHovering) {
				$div.remove();
				$div = null;
			}
		}, 500);
	}

	$(".topic_profile_link").hover(function hoverIn(e) {
		hovering = true;

		var $this = $(this);
		var uid = $this.attr('href').match(/\/([a-z0-9.]+$)/i)[1];
		var domain = $this.attr('href').match(/(^.+)\/profile/i)[1];

		$.getJSON(domain + '/profile.php?uid=' + uid + '&json=1', function(data) {
			if($div != null) {
				$div.remove();
			}

			if(!hovering) return;

			if(data.mod_notes == "") data.mod_notes = "There's nothing here!";

			var pos = $this.position();
			$div = $("<div class='body' style='position:absolute;white-space:pre'></div>");
			$div.css('top', pos.top + $this.height() + 5);
			$div.css('max-width', 400);
			$div.text(data.mod_notes);

			$div.hover(function() {
				divHovering = true;
			}, function() {
				divHovering = false;
				scheduleTimeout();
			});

			$(document.body).append($div);
			$div.css('left', pos.left - $div.width() + $this.width());
		});



	}, function hoverOut(e) {
		hovering = false;
		scheduleTimeout();
	});
});

window.showDeleted = showDeleted;
function showDeleted(id, elem){
	if($(elem).text() == "[show]"){
		$("#reply_"+id).show();
		$("#reply_box_"+id).show();
		$(elem).text("[hide]");
	}else{
		$("#reply_"+id).hide();
		$("#reply_box_"+id).hide();
		$(elem).text("[show]");
	}
}

window.replyDivToId = replyDivToId;
function replyDivToId(div) {
	if (!div) return null;

	var regexExtractId = /[0-9]+/;
	var match = regexExtractId.exec($(div).attr('id'));
	if (match) {
		return match[0];
	} else {
		return null;
	}
}

/* Browse replies. */
var replyCursor = window.replyCursor = (function() {
	var replyIds; // Use ID array for single stepping.
	var replies; // Use divs for paging.

	$(function() { // onDomReady callback.
		replyIds = $('#body_wrapper h3.c')
			.map(function(i, el) {
				return /[0-9]+/.exec(el.id);
			});

		replies = document.querySelectorAll("#body_wrapper h3");
	});

	function _replyArrayIndex() {
		var replyEl = $('#body_wrapper h3 + .body.highlighted');
		return $.inArray(replyDivToId(replyEl), replyIds);
	}

	function _nearestElement(increaseY) {
		var leeway = window.innerHeight - 80;
		var currentY = $(window).scrollTop(), targetY, candidate;
		for (var i=0; i<replies.length; i++) {
			candidate = replies[increaseY? i : replies.length - i - 1];
			targetY = $(candidate).offset().top;

			if ((increaseY && (targetY > currentY + leeway)) ||
				(!increaseY && (targetY < currentY - leeway))) {

				return candidate;
			}
		}

		if (increaseY) {
			return replies[replies.length - 1];
		} else {
			return replies[0];
		}
	}

	// Highlight the original post.
	function highlightOp() {
		$('.highlighted').each(function(i, el) {
			el.className = el.className.replace(/highlighted/, '');
		});
		$('#body_wrapper div.body')[0].className += ' highlighted';
		window.location.replace('#');
	}

	function _top() {
		highlightOp();
		window.scroll(0, 0);
	}

	// Highlight the last reply and goto the bottom of the page.
	function _bottom() {
		if (replyIds.length > 0) {
			highlightReply(replyIds[replyIds.length - 1]);
		} else { // Handle corner case of no replies.
			highlightOp();
		}
		window.scroll(0, $(document).height());
	}

	function previous() {
		var current = _replyArrayIndex();
		if (current < 0 || (current === 0 || replyIds.length === 0)) {
			_top();
		} else {
			highlightReply(replyIds[current - 1]);
		}
	}

	function next() {
		var current = _replyArrayIndex();
		if (current < 0) {
			if (replyIds.length === 0) {
				_top();
			} else {
				highlightReply(replyIds[0]);
			}
		} else if ((current + 1) >= replyIds.length) {
			_bottom();
		} else {
			highlightReply(replyIds[current + 1]);
		}
	}

	function previousScreen() {
		var replyId = replyDivToId(_nearestElement(false));
		if (replyId) {
			highlightReply(replyId);
		} else {
			_top();
		}
	}

	function nextScreen() {
		var replyId = replyDivToId(_nearestElement(true));
		if (replyId) {
			highlightReply(replyId);
		} else {
			_bottom();
		}
	}

	/*
	 * While the previous/next functions are relative to the currently active reply,
	 * the previousScreen/nextScreen functions are relative to the visible reply.
	 */
	return {
		previous: previous,
		next: next,
		previousScreen: previousScreen,
		nextScreen: nextScreen
	};
}());

/* Given a reply ID, highlight and scroll to it. */
window.highlightReply = highlightReply;
function highlightReply(id) {
	var contentEl = document.getElementById('reply_' + id);
	var boxEl = document.getElementById('reply_box_' + id);

	var divs = document.getElementsByTagName('div');
	for (var i = 0; i < divs.length; i++) {
		if (divs[i].className.indexOf('body') != -1)
			divs[i].className = divs[i].className.replace(/highlighted/, '');
	}
	if (boxEl && contentEl) {
		boxEl.className += ' highlighted';
		window.location.replace('#' + contentEl.getAttribute("name"));
	}

	if($("#reply_button_"+id).length > 0){
		$("#reply_"+id).show();
		$("#reply_box_"+id).show();
		$("#reply_button_"+id).text("[hide]");
		document.location.hash = 'reply_' + id;
		return false;
	}
	return true;
}

window.focusId = focusId;
function focusId(id) {
	document.getElementById(id).focus();
	init();
}

window.addCommas = addCommas;
function addCommas(nStr){
	nStr += '';
	var x = nStr.split('.');
	var x1 = x[0];
	var x2 = x.length > 1 ? '.' + x[1] : '';
	var rgx = /(\d+)(\d{3})/;
	while (rgx.test(x1)) {
		x1 = x1.replace(rgx, '$1' + ',' + '$2');
	}
	return x1 + x2;
}

window.quickQuote = quickQuote;
function quickQuote(id, content){
	document.getElementById('quick_reply').style.display = 'block';
	document.getElementById('qr_text').scrollIntoView(true);
	document.getElementById('qr_text').focus();
	document.getElementById('qr_text').value += '@' + addCommas(id) + '\r\n\r\n';
	document.getElementById('qr_text').value += decodeURIComponent(content.replace(/\+/g, '%20')) + "\r\n\r\n" ;
	document.getElementById('qr_text').scrollTop = document.getElementById('qr_text').scrollHeight;
	document.getElementById('qr_text').sel
	return false;
}

window.quickCite = quickCite;
function quickCite(id){
	document.getElementById('quick_reply').style.display = 'block';
	document.getElementById('qr_text').scrollIntoView(true);
	document.getElementById('qr_text').focus();
	document.getElementById('qr_text').value += '@' + addCommas(id) + '\r\n';
	document.getElementById('qr_text').scrollTop = document.getElementById('qr_text').scrollHeight;
	return false;
}

/*
 * When a table has the "selectable" class, a select box is added to the heading
 * which all the select boxes (assumed to be in the first column) shall be bound to.
 */
function initialiseSelectableTables() {
    let selectableTables = document.querySelectorAll('table.selectable');
    selectableTables.forEach(table => {
	let heading = table.querySelector('thead tr th');
	let input = document.createElement("input");
	input.type = 'checkbox';
	input.title = "Check / Uncheck all";
	input.style.display = 'inline';
	heading.insertBefore(input, heading.firstChild);
	input.addEventListener('change', e => {
	    let value = e.target.checked;
	    Array.from(table.querySelectorAll('tbody tr td:first-child input[type="checkbox"'))
		.forEach(cb => {
		    cb.checked = value;
		});
	});
    });
}

window.submitDummyForm = submitDummyForm;
function submitDummyForm(theAction, theVariableName, theVariableValue, confirmMessage) {
	if (confirmMessage === false)
		var tmp = true;
	else if (confirmMessage === undefined)
		var tmp = confirm('Really?');
	else
		var tmp = confirm(confirmMessage);
	if (tmp) {
		var form = document.getElementById('dummy_form');
		form.action = theAction;
		form.some_var.name = theVariableName;
		form.some_var.value = theVariableValue;
		form.submit();
	}
	return false;
}

window.updateCharactersRemaining  = updateCharactersRemaining;
function updateCharactersRemaining(theInputOrTextarea, theElementToUpdate, maxCharacters) {
	var tmp = document.getElementById(theElementToUpdate);
	tmp.firstChild.data = maxCharacters - document.getElementById(theInputOrTextarea).value.length;
}

window.printCharactersRemaining = printCharactersRemaining;
function printCharactersRemaining(idOfTrackerElement, numDefaultCharacters) {
	document.write(' (<STRONG ID="' + idOfTrackerElement + '">' + numDefaultCharacters + '</STRONG> characters left)');
}

var snapbackStack = [];

window.popSnapbackLink = popSnapbackLink;
function popSnapbackLink() {
	setTimeout(function() {
		snapbackStack.pop();
		var tmp = document.getElementById("snapback_link");
		if (snapbackStack.length) {
			tmp.href = '#reply_' + snapbackStack[snapbackStack.length - 1];
			document.querySelector('#snapback_link span').innerHTML = snapbackStack.length > 1? snapbackStack.length : '';
		} else {
			tmp.style.display = 'none';
		}
	}, 10);
	return true;
}

window.createSnapbackLink = createSnapbackLink;
function createSnapbackLink(lastReplyId) {
	if (snapbackStack.length && snapbackStack[snapbackStack.length - 1] === lastReplyId) {
		return;
	}

	snapbackStack.push(lastReplyId);
	var a = document.getElementById('snapback_link');
	var linkCounter = document.querySelector('#snapback_link span');

	a.href = '#reply_' + lastReplyId;
	a.style.display = 'inline';
	document.querySelector('#snapback_link span').innerHTML = snapbackStack.length > 1? snapbackStack.length : '';
}

window.getCookie = getCookie;
function getCookie(c_name) {
	var i, x, y, ARRcookies = document.cookie.split(";");
	for (i = 0; i < ARRcookies.length; i++) {
		x = ARRcookies[i].substr(0, ARRcookies[i].indexOf("="));
		y = ARRcookies[i].substr(ARRcookies[i].indexOf("=") + 1);
		x = x.replace(/^\s+|\s+$/g, "");
		if (x == c_name) {
			return unescape(y);
		}
	}
}

window.setCookie = setCookie;
function setCookie(cookieName,cookieValue,nDays) {
	var today = new Date();
	var expire = new Date();
	if (nDays==null || nDays==0) nDays=1;
	expire.setTime(today.getTime() + 3600000*24*nDays);
	document.cookie = cookieName+"="+escape(cookieValue)
				 + ";expires="+expire.toGMTString();
}

window.chooseImage = chooseImage;
function chooseImage(elem) {
	var url = prompt("Imgur url to attach?");
	if(url == "" || url == null)
		return false;

	submitDummyForm(elem.href, 'url', url, false);
	return false;
}

window.highlightReplyFromHash = highlightReplyFromHash;
function highlightReplyFromHash() {
	if (window.location.hash && document.getElementById(window.location.hash.substring(1)) && window.location.hash.indexOf('reply_') != -1)
		highlightReply(window.location.hash.substring(7));
	else if (window.location.hash.indexOf('new') != -1)
		highlightReply(document.getElementById('new_id').value);
	else
		$("div.highlighted").removeClass("highlighted");
}

window.init = init;
function init() {
	$(window).on('hashchange', function() {
		highlightReplyFromHash();
	});
	highlightReplyFromHash();
	window['UID'] = getCookie("UID");
	if(!getCookie('fp')) setCookie('fp', new Fingerprint({canvas: true}).get(), 7);
	$("form").submit(function() {
		var $inputs = $("input, button, textarea", this);
		setTimeout(function() {
			// This must run in the next tick or the input elem is not included in request
			$inputs.prop("disabled", true);
		}, 1);
		setTimeout(function() {
			$inputs.prop("disabled", false);
		}, 3000);
	});

    if($("body").hasClass("page-index") && $("tr.ignored").length > 0) {
        var $toggleIgnoredLink = $("<a href='#' class='show_ignored_link'>(show ignored)</a>");
        $("#body_title").append($toggleIgnoredLink);

        $toggleIgnoredLink.click(function(e) {
           e.preventDefault();
           if($toggleIgnoredLink.text() == "(show ignored)") {
               $toggleIgnoredLink.text("(hide ignored)");
               $("tr.ignored").show();
           }else{
               $toggleIgnoredLink.text("(show ignored)");
               $("tr.ignored").hide();
           }
        });
    }else if($("body").hasClass("page-post") || $("body").hasClass("page-topic")) {
        $(window).off("beforeunload").on("beforeunload", function() {
            if($("#body").val() || $("#qr_text").val()) {
                return "Your changes be lost if you leave this page.";
            }
        });

        $("form").submit(function() {
           $(window).off("beforeunload");
        });
    }

    $("a.thickbox").click(function(e) {
        if(e.button != 0) return;
        e.preventDefault();
        var $this = $(this);
        var $img = $("img", this);

        if($img.hasClass("img-loading")) return;

        if($img.hasClass("img-expanded")) {
			$img.css({
				'width': $img.data('width'),
				'float': 'left',
				'display': ''
			});
			$img.attr("src", $img.data("src"));
			$img.removeClass("img-expanded");
			$("video", $this).remove();
			$img.show();
        }else{
            var videoRegex = /\.(webm|gifv)$/;
            var isVideo = $this.attr("href").match(videoRegex);

			if(!$img.data("storedData")) {
				$img.data("width", $img.attr("width"));
				$img.data("height", $img.attr("height"));
				$img.data("src", $img.attr("src"));
				$img.data("isVideo", isVideo);
				$img.data("storedData", true);
			}

            $img.addClass("img-expanded");

            if(!isVideo) {
                $img.addClass("img-loading");

                var preload = new Image();

                $(preload).on("load", function() {
                    $img.attr("width", "");
                    $img.attr("height", "");

                    $img.attr("src", $this.attr("href"));

					$img.css({
						'width': preload.width+'px',
						'float': 'none',
						'display': 'block',
                        'max-width': '100%'
					});
                    $img.removeClass("img-loading");
                });

                $(preload).on("error", function() {
                    $img.removeClass("img-loading");
                });

                preload.src = $this.attr("href");
            }else{
                $img.hide();
                var url = $this.attr("href").replace(videoRegex, "") + ".webm";
                var $video = $("<video />");
                $video.css("margin-bottom", $img.css("margin-bottom"));
                $video.css("margin-right", $img.css("margin-right"));
                $video.css("max-width", "100%");
                $video.css("float", "none");
                $video.css("display", "block");
                $video.attr("src", url);
                $video.attr("autoplay", true);
                $video.attr("loop", true);
                $img.after($video);
            }
        }
    });
}

$(init);
$(initialiseSelectableTables);

window.submitSetTime = submitSetTime;
function submitSetTime(el) {
        var time = prompt("New last bump time?");
        if(!time) time = "now";
        var form = document.getElementById('dummy_form');
        form.action = el.href;
        form.some_var.name = "time";
        form.some_var.value = time;
        form.submit();
        return false;
}
