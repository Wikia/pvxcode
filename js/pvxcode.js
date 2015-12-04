var prevX, prevY, prevId=0, clicked=false, new_click=true, sav_event, d=document;

function switchDiv(id, frame, load) {
   // Initialize menu and loading frame sources
   var menu = iniMenu(frame);

   if(load == menu.alt)
      // Switch from visible to hidden and back if we clicked on that icon before
      menu.style.display = (menu.style.display=="none")?"":"none";
   else
      initDescription(frame, load);
   menu.alt = load;
};

function initDescription(frame, load) {
   // Copy a new description and show it
   var menu = iniMenu(frame);

   if(document.getElementById && !(d.all))
      menu.innerHTML = d.getElementById('load'+load).innerHTML;
   else if(d.all)
      menu.innerHTML = d.frames['load'+load].innerHTML;

   menu.style.display = "";
};


//PICKUP
////////

function pickup(action, id) {
   //Update database
   div('send').src = 'extensions/PvXCode/vendor/gwbbcode/pickup.php?'+action+'='+id+'&rand=' + Math.round(1000*Math.random());

   //Switch between Add and Remove links
   if (action != 'switch') {
      var opp_action = (action=='remove') ? 'add' : 'remove';
      div(action+'_'+id).style.display = 'none';
      div(opp_action+'_'+id).style.display = '';
   }
}

function pickup_set(userlist, id) {
   var divs = document.getElementsByTagName('span');
   for (var i=0; i<divs.length; i++)
      if ((typeof(divs[i].id) != 'undefined') && (divs[i].id == 'pickup_'+id))
         divs[i].innerHTML = userlist;
}


function iniMenu(frame) {
   if(d.getElementById && !(d.all))
      return d.getElementById('show'+frame);
   else if(d.all)
      return d.all['show'+frame];
};

function div(name) {
   var d = document;
   if(d.getElementById && !(d.all))
      return d.getElementById(name);
   else if(d.all)
      return d.all[name];
};


//TEMPLATE

function switch_template(load) {
   var style = div('gws_template'+load).style;
   if (style.display == 'none') {
      //Show selected template code
      style.display = '';
      div('gws_template_input'+load).select();
      div('gws_template_input'+load).focus();
      //and hide all others
      var divs = document.getElementsByTagName('DIV');
      for (var i=0; i<divs.length; i++) {
         if (   /^gws_template[0-9]/.test(divs[i].id)
             && divs[i].id != 'gws_template'+load
             && divs[i].style.display == '') {
            switch_template(divs[i].id.match(/\d+/)[0]);
         }
      }
   }
   else {
      style.display = 'none';
   }
   return false;
}


//JavaScript Function by Shawn Olson
//Copyright 2004
//http://www.shawnolson.net
//If you copy any functions from this page into your scripts, you must provide credit to Shawn Olson & http://www.shawnolson.net
//*******************************************

function change_css(theClass,element,value) {
   //documentation for this script at http://www.shawnolson.net/a/503/
   var cssRules;
   if (document.all)
      cssRules = 'rules';
   else if (document.getElementById)
      cssRules = 'cssRules';

   for (var S = 0; S < document.styleSheets.length; S++)
      for (var R = 0; R < document.styleSheets[S][cssRules].length; R++)
         if (document.styleSheets[S][cssRules][R].selectorText == theClass)
            document.styleSheets[S][cssRules][R].style[element] = value;
}
