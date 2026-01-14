<?php
/*
All Emoncms code is released under the GNU Affero General Public License.
See COPYRIGHT.txt and LICENSE.txt.

Emoncms - open source energy visualisation
Part of the OpenEnergyMonitor project: http://openenergymonitor.org
*/
    defined('EMONCMS_EXEC') or die('Restricted access');  // no direct access
    global $path;
?>
<?php
    load_language_files(dirname(__DIR__).'/locale',"vis_messages");
?>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.selection.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.touch.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.togglelegend.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.time.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.stack.min.js"></script>

<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.canvas.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/plugin/saveAsImage/lib/base64.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/plugin/saveAsImage/lib/canvas2image.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/plugin/saveAsImage/jquery.flot.saveAsImage.js"></script>

<script type="text/javascript"><?php require "Modules/vis/vis_langjs.php"; ?></script>

<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Modules/feed/feed.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/vis.helper.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Modules/vis/visualisations/multigraph/multigraph.js?v=2"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Modules/vis/Views/multigraph_api.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Modules/vis/Views/multigraph_edit.js?v=3"></script>

<link href="<?php echo $path; ?>Lib/bootstrap-datetimepicker-0.0.11/css/bootstrap-datetimepicker.min.css" rel="stylesheet">
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/bootstrap-datetimepicker-0.0.11/js/bootstrap-datetimepicker.min.js"></script>

<h2><?php echo ctx_tr('vis_messages','Visualisations'); ?></h2>
<div id="vispage">
<table><tr valign="top"><td>
<!--increase width of container to fit the text and dropdown on the same line-->
    <div style="width:350px; background-color:#efefef; border: 1px solid #ddd; border-radius: 8px;">
        <div style="padding:5px;  border-top: 1px solid #fff">
          
            <div style="float:left; padding-top:2px; font-weight:bold; margin-left: 5px;"><?php echo ctx_tr('vis_messages','Select visualisation:')?> </div>
            <div style="float:right;"><span id="select"></span></div>
            <div style="clear:both"></div>
        </div>
        <div style="padding:5px;  border-top: 1px solid #fff">
            <div style="padding-top:2px; font-weight:bold; margin-left: 5px;"><?php echo ctx_tr('vis_messages','Set options:')?> </div><br>
            <div id="box-options" ></div><br>
            <!-- Added note to check feed type if feed not appearing in selection box -->
            <div style="
              border:1px solid #f7bda6;
              background:#fff4e5;
              padding:10px 12px;
              border-radius:6px;
              font-size:12px;
              color:#666;
              margin:10px;
            ">
              <span style="font-weight:bold; margin-right:8px;">ℹ</span>
              <?php echo ctx_tr('vis_messages','If a feed does not appear in the selection box, check that the type has been set on the feeds page.'); ?>
            </div>


        </div>
        <div style="padding:5px;  border-top: 1px solid #fff">
            <div style="float:left; padding-top:2px; font-weight:bold; margin-left: 8px;"></div>
            <div style="floating:right;">
                <button id="viewbtn" type="button" class="btn btn-info" style="width:140px; justify-content: center; padding:8px 20px; border-radius:6px; font-weight:bold;">
                  <i style="margin-right:6px;"></i>
                  <span style="margin-right:6px;">👁</span> <?php echo ctx_tr('vis_messages','View'); ?>
                </button>

                <button id="fullscreen" type="button" class="btn btn-success" style="width:140px;padding:8px 20px; border-radius:6px; font-weight:bold; float: right; margin-right: 8px;">
                  <span style="margin-right:6px;">⤢</span>
                  <?php echo ctx_tr('vis_messages','Full screen'); ?>
                </button>

                <button id="minimizebtn" type="button" class="btn btn-warning" style="display:none; width:140px;padding:8px 20px; border-radius:6px; font-weight:bold; float: right; margin-right: 8px;">
                  <i class="icon-resize-small" style="margin-right:6px; color: #ddd; "></i>
                  Minimize
                </button>

            </div>
            <div style="clear:both"></div>
        </div>
        <div style="padding:5px;  border-top: 1px solid #fff">
            <div style="padding-top:2px; font-weight:bold; margin-left: 5px;"><?php echo ctx_tr('vis_messages','Embed in your website:'); ?> </div><br>

            <!--Modified the textarea: added box, copy button, scroll horizontally-->
            <!-- Embed info / warning box (hidden by default) -->
            <div id="embedNote" style="
              display:none;
              border:1px solid #e0c7b3;
              background:#fbf6f1;
              padding:10px 12px;
              border-radius:6px;
              font-size:12px;
              color:#444;
              margin-top:10px;
            ">
              <span style="font-weight:bold; margin-right:8px;">🔒</span>
              <span id="embedNoteText"></span>
            </div>

            <div style="margin-top:10px; font-weight: lighter; margin-left:5px; font-size: 14px;">
              <?php echo ctx_tr('vis_messages','To embed privately:'); ?>
            </div>

            <!-- Embed code container -->
            <div style="
              position: relative;
              border:1px solid #ddd;
              background:#fff;
              padding:10px 10px 12px 10px;
              border-radius:6px;
              margin-top:8px;
            ">

              <!-- Copy button -->
              <button id="copyEmbedBtn" type="button" class="btn btn-small" style="
                position:absolute;
                top:8px;
                right:8px;
                border-radius:6px;
                margin-top:5px;
                margin-right:5px;
              "><span style="margin-right: 0px;">📋</span>
              Copy</button>

              <!-- Code box (horizontal scroll) -->
              <pre id="embedcode" style="
                margin:0;
                padding:34px 8px 8px 8px;  /* space for Copy button */
                font-size:12px;
                line-height:1.4;
                white-space: pre;          /* keep formatting */
                overflow-x: auto;          /* horizontal scroll */
                overflow-y: hidden;        /* no vertical scroll */
                max-width: 340px;
              "></pre>
            </div>


        </div>
    </div>
</td><td style="padding:0px 0px 0px 5px;">
    <div id="vis_bound">
        <div id="visiframe" style="border: 1px solid #ddd;"></div>
    </div>
</td></tr></table>
</div>
<div id="visurl"></div>

<!-- Added fullscreen mode styling -->
<style>
/* Fullscreen state */
#vispage.fullscreen {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 9999;
  background: #fff;
  padding: 10px;
  overflow: hidden;
}

/* FLIP animation support */
#vispage {
  transform-origin: top left;
  will-change: transform;
}

#vispage.animating {
  transition: transform 0.4s ease-in-out;
}

/* Optional: fade iframe during redraw */
#visiframe {
  opacity: 1;
  transition: opacity 0.2s ease;
}
#visiframe.fading {
  opacity: 0;
}

#vispage.fullscreen #vis_bound {
  width: 100% !important;
  height: calc(100% - 40px) !important;
}


/* Fade effect for iframe redraw */
/* #visiframe {
  opacity: 1;
  transition: opacity 200ms ease; */

#visiframe.fading {
  opacity: 0;
}

#vispage.fullscreen #vis_bound {
  width: 100% !important;
  height: calc(100% - 40px) !important;
}
</style>



<script type="application/javascript">
  var feedlist = <?php echo json_encode($feedlist); ?>;
  var widgets = <?php echo json_encode($visualisations); ?>;
  var embed = 0;
  var apikey = "";
  //var apikey = "<?php echo $apikey; ?>";
  
  var out = '<select id="visselect" style="width:160px; margin:0px;">';
  for (z in widgets) {
    // If widget action specified: use action otherwise override with widget key
    var action = z;
    var label = z;
    if (widgets[z]['action']!=undefined) action = widgets[z]['action'];
    if (widgets[z]['label']!=undefined) label = widgets[z]['label'];
    out += "<option value='"+action+"' >"+label+"</option>";
  }
  out += '</select>';
  $("#select").html(out);

  draw_options(widgets['realtime']['options']);

  vis_resize();
    
  // --- Actions
  $("#visselect").change(function() {
    // Custom multigraph visualisation items
    if ($(this).val()=="multigraph") {
      multigraphGUI();
    } else {
      $("#viewbtn").show();
      // Normal visualisation items
      draw_options(widgets[$(this).val()]['options'], widgets[$(this).val()]['optionstype']);
    }
  });

  $("#viewbtn").click(function(){
    var vistype = $("#visselect").val();
    visurl = path+"vis/"+vistype;

    // Here we go through all the options that are set and get their values creating a url string that gets the
    // visualisation. We also check for each feed if the feed is a public feed or not.
    // If the feed is not public then we include the read apikey in the embed code box.
    var publicfeed = 1;
    var options = [];
    $(".options").each(function() {
      if ($(this).val()) {
        if ($(this).attr("type")=="color") {
          // Since colour values are generally prefixed with "#", and "#" isn't valid in URLs, we strip out the "#".
          // It will be replaced by the value-checking in the actual plot function, so this won't cause issues.
          var colour = $(this).val();
          colour = colour.replace("#","");
          options.push($(this).attr("id")+"="+colour);
        } else {
          options.push($(this).attr("id")+"="+encodeURIComponent($(this).val()));
        }
        if ($(this).attr("otype")=='feed') publicfeed = $('option:selected', this).attr('public');
      }
    });
    visurl += "?"+options.join("&");
    
    vis_resize();
    var width = $("#visiframe").width();
    var height = $("#visiframe").height();

    $("#visiframe").html('<iframe style="width:'+width+'px; height:'+height+'px;" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="'+visurl+'&embed=1"></iframe>');
    if (publicfeed == 1) {
      $("#embedNote").hide();
      $("#embedNoteText").text("");
      $("#embedcode").text('<iframe style="width:580px; height:400px;" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="'+visurl+'&embed=1"></iframe>');
    } else {
      // Show warning note box (like screenshot)
      $("#embedNote").show();
      $("#embedNoteText").text("Some of the feeds selected are not public. To embed a visualization publicly, first make the feeds public on the feeds page.");

      // Show private embed instructions in code box
      $("#embedcode").text(
        '<iframe style="width:580px; height:400px;" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="'+visurl+'&embed=1&apikey='+apikey+'"></iframe>'
      );
    }

  });

  //Edit the fullscreen button to not open a new window, but rather expand the current view to fullscreen
$("#fullscreen").click(function () {
  var $vis = $("#vispage");

  // 1) FIRST: measure current box
  var first = $vis[0].getBoundingClientRect();

  // Enter fullscreen state (final layout)
  $vis.addClass("fullscreen");

  // (optional) fade menus as you already do
  $("body").addClass("vis-fullscreen");

  // 2) LAST: measure final box
  var last = $vis[0].getBoundingClientRect();

  // 3) INVERT: apply transform so it LOOKS like it never moved
  var dx = first.left - last.left;
  var dy = first.top - last.top;
  var sx = first.width / last.width;
  var sy = first.height / last.height;

  $vis.addClass("animating");
  $vis.css("transform", "translate(" + dx + "px," + dy + "px) scale(" + sx + "," + sy + ")");

  // Force reflow so transform is applied before animating
  $vis[0].offsetHeight;

  // 4) PLAY: animate to the real fullscreen position
  $vis.css("transform", "translate(0px,0px) scale(1,1)");

  // swap buttons
  $("#fullscreen").hide();
  $("#minimizebtn").show();

  // After animation finishes, clean up
  setTimeout(function () {
    $vis.removeClass("animating");
    $vis.css("transform", "");

    // Resize iframe AFTER container reaches fullscreen (cleaner)
    var width = $("#vispage").width();
    var height = $("#vispage").height() - 40;

    var iframe = $("#visiframe").children("iframe");
    if (iframe.length) {
      iframe.width(width);
      iframe.height(height);
    } else {
      // If no iframe yet, trigger render
      $("#viewbtn").trigger("click");
      $("#visiframe").children("iframe").width(width).height(height);
    }
  }, 420);
});



  $("#minimizebtn").click(function () {
  var $vis = $("#vispage");

  // FIRST: measure fullscreen box
  var first = $vis[0].getBoundingClientRect();

  // Remove fullscreen state (final layout back to normal)
  $vis.removeClass("fullscreen");
  $("body").removeClass("vis-fullscreen");

  // LAST: measure normal box
  var last = $vis[0].getBoundingClientRect();

  // INVERT
  var dx = first.left - last.left;
  var dy = first.top - last.top;
  var sx = first.width / last.width;
  var sy = first.height / last.height;

  $vis.addClass("animating");
  $vis.css("transform", "translate(" + dx + "px," + dy + "px) scale(" + sx + "," + sy + ")");

  $vis[0].offsetHeight;

  // PLAY
  $vis.css("transform", "translate(0px,0px) scale(1,1)");

  $("#fullscreen").show();
  $("#minimizebtn").hide();

  setTimeout(function () {
    $vis.removeClass("animating");
    $vis.css("transform", "");

    // Restore normal sizing + rerender
    vis_resize();
    $("#viewbtn").trigger("click");
  }, 420);
});





  $(window).resize(function(){vis_resize();});


  // --- Functions
  function draw_options(box_options) {
    // Build options table html
    var options_html = "";
    for (z in box_options) {
      options_html += "<div class='input-prepend'><span class='add-on' style='width: 100px; text-align: right; font-size:12px'>"+box_options[z][1]+"</span>";
      var type = box_options[z][2];

      if (type == 0 || type == 1 || type == 2 || type == 3) {
        options_html += selectFeed(box_options[z][0], feedlist, type);
      } else if (type == 4)  { // boolean
        options_html += "<select class='options' id='"+box_options[z][0]+"'><option value='0'" + (box_options[z][3] == 0 ? " selected" : "") + "><?php echo ctx_tr('vis_messages','Off');?></option><option value='1'" + (box_options[z][3] == 1 ? " selected" : "") + "><?php echo ctx_tr('vis_messages','On');?></option></select>";
      } else if (type == 9)  { // colour
        options_html += "<input type='color' class='options' id='"+box_options[z][0]+"' value='#"+box_options[z][3]+"'>";
      } else if (type == 8)  {
        //added by Alexandre CUER - for psychrographic diagrams
        multigraphs_name = [];
        multigraphs = multigraph.getlist();
        var options = "";
        for (indice in multigraphs) {
            multigraphs_name[multigraphs[indice]['id']] = multigraphs[indice]['name'];
            options +="<option value='"+multigraphs[indice]['id']+"'>"+multigraphs[indice]['id']+": "+multigraphs[indice]['name']+"</option>";
        }
        var out = "<option>create a multigraph with a Temp. feed and a RH feed</div>";
        if (options){
            out = "<option>Select multigraph:</option>"+options;
        }
        options_html += "<select class='options' id='mid'>"+out+"</select>";
      } else {
        options_html += "<input type='text' class='options' id='"+box_options[z][0]+"' value='"+box_options[z][3]+"'>";
      }
      options_html += "</div>";
    }
    options_html += "";

    $("#box-options").html(options_html);

    // Change the size of the text for items with class options - size initially set by bootstrap
    // also add height of 30 px for color inputs for Firefox
    $("input[class='options'], select[class='options'], textarea[class='options']").css({'font-size':'12px'});

    // change the code below so that the resizing apply to all browser, not only firefox
    // if (navigator.userAgent.search("Firefox") >= 0) {$("input[type='color']").css({'height':'30px','width':'220px'});};
    $("input[type='color'].options").css({'height':'30px','width':'220px'});

  }

  // Create a drop down select box with a list of feeds.
  function selectFeed(id, feedlist, type) {
    var feedgroups = [];
    for (z in feedlist) {
      var group = (feedlist[z].tag === null ? "NoGroup" : feedlist[z].tag);
      if (group!="Deleted") {
        if (!feedgroups[group]) feedgroups[group] = []
        feedgroups[group].push(feedlist[z]);
      }
    }
    var out = "<select id='"+id+"' class='options' otype='feed'>";
    for (z in feedgroups) {
      out += "<optgroup label='"+z+"'>";
      for (p in feedgroups[z]) {
        out += "<option value="+feedgroups[z][p]['id']+" public="+feedgroups[z][p]['public']+">"+feedgroups[z][p].id+": "+feedgroups[z][p].name+"</option>";
      }
      out += "</optgroup>";
    }
    out += "</select>";
    return out;
  }

  function vis_resize() {
    var width = $("#vispage").width() - 349;
    if (width < 320) width = 320;
    var height = width * 0.5625;
    var vistype = $("#visselect").val();
    if (vistype == "compare" || vistype == "psychrograph") height = height * 3;
    $("#vis_bound").width(width);
    $("#vis_bound").height(height);
    $("#visiframe").width(width);
    $("#visiframe").height(height);
    var iframe = $("#visiframe").children('iframe');
    iframe.width(width);
    iframe.height(height);
  }
</script>