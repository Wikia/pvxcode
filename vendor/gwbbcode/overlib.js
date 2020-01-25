// This script relies on gwbbcode.tpl putting unique ids on each hidden tooltip node,
// and calling the global functions from the visible parent (such as an icon or text)
// - not sure why the original script was quite so long, and why this is so short.

// Link to original script: http://www.bosrup.com/web/overlib/

// Chieftain_Alex, 2020 January.

function overlib2() {
    // Add fixed position element in top left
    var hoverdiv = document.createElement('div');
    hoverdiv.id = 'gwpvxhoverdiv';
    hoverdiv.style.position = 'absolute';
    hoverdiv.style.top = '-5000px';
    hoverdiv.style.left = '-5000px';
    document.body.appendChild(hoverdiv);
    
    // New global function - Track cursor and move tooltip nearby
    window.moveTooltip = function(e) {
        document.getElementById('gwpvxhoverdiv').style.left = e.pageX + 'px';
        document.getElementById('gwpvxhoverdiv').style.top = e.pageY + 'px';
    }

    // New global function - Populate and position the tooltip, and start tracking movement
    window.pvxTooltipOpen = function(content) {
        document.getElementById('gwpvxhoverdiv').innerHTML = content;
        document.addEventListener('mousemove', moveTooltip);        
    }

    // New global function - Reposition the tooltip out of sight, and stop tracking movement
    window.pvxTooltipClose = function() {
        document.getElementById('gwpvxhoverdiv').style.top = '-5000px';
        document.removeEventListener('mousemove', moveTooltip);
    }
}
overlib2();
