/**
 * PvX Code
 * Guildwiki / Guild Wars Template to PvXCode Handling
 *
 * @author	Chieftain_Alex
 * @license	GNU General Public License v2.0 or later
 * 
 * Purpose of this file:
 *  Insert content of hidden divs into a tooltip which follows the cursor.
 * 
 * Note:
 *  This script relies on gwbbcode.tpl putting unique identifiers on each hidden tooltip node.
  * 
 *  Link to original script: http://www.bosrup.com/web/overlib/
**/
function overlib2() {
	// Add fixed position element in top left
	var hovertooltipparent = document.createElement('div');
	hovertooltipparent.id = 'hovertooltip-parent';
	hovertooltipparent.style.position = 'absolute';
	hovertooltipparent.style.top = '-5000px';
	hovertooltipparent.style.left = '-5000px';
	document.body.appendChild(hovertooltipparent);

	// Helper function - Track cursor and move tooltip nearby
	function moveTooltip(e) {
		// Avoid possible collision with right-hand side of page
		var w = window.innerWidth;
		if (w !== null && e.pageX + 20 + 420 > w) {
			document.getElementById('hovertooltip-parent').style.left = (e.pageX - (20 + 420)) + 'px';
		} else {
			document.getElementById('hovertooltip-parent').style.left = (e.pageX +20) + 'px';
		}
		document.getElementById('hovertooltip-parent').style.top = (e.pageY +20) + 'px';
	}

	// Bind events to all elements with the class "hovertooltip"
	var elements = document.getElementsByClassName('hovertooltip');
	var content;
	for (var i = 0; i < elements.length; i++) {
		// Populate and position the tooltip, and start tracking movement
		elements[i].addEventListener('mouseenter', function(e) {
			var tid = e.target.dataset.tooltipid;
			content = document.getElementById(tid).innerHTML;
			document.getElementById('hovertooltip-parent').innerHTML = content;
			document.addEventListener('mousemove', moveTooltip);
		});

		// Reposition the tooltip out of sight, and stop tracking movement
		elements[i].addEventListener('mouseleave', function(e) {
			document.getElementById('hovertooltip-parent').style.top = '-5000px';
			document.removeEventListener('mousemove', moveTooltip);
		});
	}
}
overlib2();
