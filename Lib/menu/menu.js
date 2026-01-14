var menu = {

    // Holds the menu object collated from the _menu.php menu definition files
    obj: {},

    // Menu visibility and states
    // These do not currently control the state from startup but are set during startup
    menu_top_visible: true,
    l2_visible: false,
    l3_visible: false,
    l2_min: false,

    last_active_l1: false,

    active_l1: false,
    active_l2: false,
    active_l3: false,
    
    mode: 'auto',
    is_disabled: false,

    debug: false,

    auto_hide: true,    
    auto_hide_timer: null,
    
    // ------------------------------------------------------------------
    // Init Menu
    // ------------------------------------------------------------------    
    init: function(obj,public_username) {
        menu.init_time = new Date().getTime()
    
        var q_parts = q.split("#");
        q_parts = q.split("?");
        q_parts = q_parts[0].split("/");
        var controller = false; 
        if (public_username) {
            if (q_parts[1]!=undefined) controller = q_parts[0]+"/"+q_parts[1];
        } else {
            if (q_parts[0]!=undefined) controller = q_parts[0];
        }
        
        menu.obj = obj;

        // Set initial sidebar state BEFORE drawing menus
        // Always start with expanded sidebar for modern layout
        menu.l2_min = false;
        menu.l2_visible = true;

        // Detect and merge in any custom menu definition created by a view
        if (window.custom_menu!=undefined) {
            for (var l1 in custom_menu) {
                menu.obj[l1]['l2'] = custom_menu[l1]['l2']
            }
        }

        // Detect l1 route on first load
        for (var l1 in menu.obj) {
            if (menu.obj[l1]['l2']!=undefined) {
                for (var l2 in menu.obj[l1]['l2']) {
                    if (menu.obj[l1]['l2'][l2]['l3']!=undefined) {
                        for (var l3 in menu.obj[l1]['l2'][l2]['l3']) {
                            if (menu.obj[l1]['l2'][l2]['l3'][l3].href.indexOf(controller)===0) {
                                menu.active_l1 = l1;
                            }
                        }
                    } else {
                        if (menu.obj[l1]['l2'][l2].href.indexOf(controller)===0) {
                            menu.active_l1 = l1;
                        }
                    }
                }
            } else {
                if (menu.obj[l1].href!=undefined && menu.obj[l1].href.indexOf(controller)===0) menu.active_l1 = l1;
            }
        }

        menu.log("init: draw_l1, events, resize");
        menu.draw_l1();
        menu.events();
        
        // Modern layout - sidebar expanded by default, collapsible
        menu.l3_visible = false;

        // Start with expanded sidebar, disable transitions during init
        menu.disable_transition(".menu-l2");
        menu.disable_transition(".content-container");

        if ($(window).width() >= 576) {
            menu.l2_min = false;
            menu.l2_visible = true;
            menu.exp_l2();
        } else {
            menu.hide_l2();
        }

        menu.hide_l3();
        menu.resize();

        menu.enable_transition(".menu-l2");
        menu.enable_transition(".content-container");
    },

    // ------------------------------------------------------------------    
    // L1 menu is the top bar menu
    // ------------------------------------------------------------------    
    draw_l1: function () {
        menu.log("draw_l1");
        // Build level 1 menu (top bar)
        var out = "";
        for (var l1 in menu.obj) {
            let item = menu.obj[l1]
            // Prepare active status
            let active = ""; if (l1==menu.active_l1) active = "active";
            // Prepare icon
            let icon = '<svg class="icon '+item['icon']+'"><use xlink:href="#icon-'+item['icon']+'"></use></svg>';
            // Title
            if (item['name']!=undefined) {
                let title = item['name'];
                if (item['title']!=undefined) title = item['title'];
                // Menu item
                let href='';
                if (item['default']!=undefined) {
                    href = 'href="'+path+item['default']+'"';
                }
                out += '<li><a '+href+' onclick="return false;"><div l1='+l1+' class="'+active+'" title="'+title+'"> '+icon+'<span class="menu-text-l1"> '+item['name']+'</span></div></a></li>';
            }
        }
        $(".menu-l1 ul").html(out);
        
        if (menu.active_l1 && menu.obj[menu.active_l1]['l2']!=undefined) { 
            menu.log("draw_l1: draw_l2");
            menu.draw_l2();
        } else { 
            menu.log("draw_l1: hide_l2");
            menu.disable_transition(".menu-l2");
            menu.hide_l2();
            menu.enable_transition(".menu-l2");
        }
    },

    // ------------------------------------------------------------------
    // Level 2 (Sidebar)
    // ------------------------------------------------------------------
    draw_l2: function () {
        menu.log("draw_l2");
        // Sort level 2 by order property
        // build a set of keys first, sort these and then itterate through sorted keys
        var keys = Object.keys(menu.obj[menu.active_l1]['l2']);
        keys = keys.sort(function(a,b){
            return menu.obj[menu.active_l1]['l2'][a]["order"] - menu.obj[menu.active_l1]['l2'][b]["order"];
        });

        // Build level 2 menu (sidebar) with EnergyMon brand header
        var out = `
          <div class="sidebar-brand">
            <div class="brand-icon">
              <img src="${path}Theme/emoncms-logo.png" alt="Emoncms" style="width: 100%; height: 100%; object-fit: contain;">
            </div>
            <div class="brand-text">Emoncms</div>
          </div>
        `;
        
        for (var z in keys) {
            let l2 = keys[z];
            let item = menu.obj[menu.active_l1]['l2'][l2];
            
            if (item['divider']!=undefined && item['divider']) {
                out += '<li style="height:'+item['divider']+'"></li>';
            } else {
                let active = ""; 
                if (q.indexOf(item['href'])===0) { 
                    active = "active"; 
                    menu.active_l2 = l2;
                }
                // Prepare icon
                let icon = "";
                if (item['icon']!=undefined) {
                    icon = '<svg class="icon '+item['icon']+'"><use xlink:href="#icon-'+item['icon']+'"></use></svg>';
                }
                
                // Title
                let title = item['name'];
                if (item['title']!=undefined) title = item['title'];

                // Create link if applicable
                let href = ''
                if (item['l3']==undefined) {
                    href = 'href="'+path+item['href']+'"'
                } else {
                    if (item['default']!=undefined) {
                        href = 'href="'+path+item['default']+'"'
                    }
                }
                // Disable link for active menu items
                if (active=="active") href = '';
                
                // Menu item                
                out += '<li><a '+href+'><div l2='+l2+' class="'+active+'" title="'+title+'"> '+icon+'<span class="menu-text-l2"> '+item['name']+'</span></div></a></li>';
            }
        }
        
        $(".menu-l2 ul").html(out);

        if (menu.l2_min) {
            $(".menu-text-l2").hide();
            $(".sidebar-brand .brand-text").hide();
        } else {
            $(".menu-text-l2").show();
            $(".sidebar-brand .brand-text").show();
        }

        // In EnergyMon layout, don't auto-expand on draw
        // Let init() control the initial state
    },

    // ------------------------------------------------------------------
    // Level 3 (Sidebar submenu) - DISABLED in EnergyMon layout
    // ------------------------------------------------------------------
    draw_l3: function () {
        // L3 is not used in EnergyMon layout
        menu.log("draw_l3: disabled in EnergyMon layout");
    },
    
    hide_menu_top: function () {
        $(".menu-top").addClass("menu-top-hide");
        menu.menu_top_visible = false;
    },
    
    show_menu_top: function () {
        $(".menu-top").removeClass("menu-top-hide");
        menu.menu_top_visible = true;
    },

    hide_l1: function () {
        $(".menu-l1").hide();
    },

    hide_l2: function () {
        clearTimeout(menu.auto_hide_timer);
        if (menu.l3_visible) {
            menu.log("hide_l2: hide_l3");
            menu.hide_l3();
        }

        if (menu.l2_visible) {
            $(".menu-text-l2").hide();
            $(".sidebar-brand .brand-text").hide();
            $(".menu-l2").css("width","0px");
            var ctrl = $("#menu-l2-controls");
            //ctrl.removeClass("ctrl-exp").removeClass("ctrl-min").addClass("ctrl-hide");
        }

        $(".content-container").css("margin-left","0");
        menu.l2_visible = false;
    },

    min_l2: function () {
        clearTimeout(menu.auto_hide_timer);
        if (!(menu.l2_visible && menu.l2_min)) {
            $(".menu-l2").css("width","50px");

            $(".menu-text-l2").hide();
            $(".sidebar-brand .brand-text").hide();
            var ctrl = $("#menu-l2-controls");
            ctrl.html('<svg class="icon"><use xlink:href="#icon-expand"></use></svg>');
            //ctrl.attr("title",_Tr_Menu("Expand sidebar")).removeClass("ctrl-hide").removeClass("ctrl-exp").addClass("ctrl-min");
        }

        var window_width = $(window).width();
        var max_width = $(".content-container").css("max-width").replace("px","");
        if (max_width=='none' || window_width<max_width) {
            $(".content-container").css("margin-left","50px");
        } else {
            $(".content-container").css("margin-left","50px");
        }

        menu.l2_min = true;
        menu.l2_visible = true;
    },

    // If we expand l2 we also hide l3
    exp_l2: function () {
        clearTimeout(menu.auto_hide_timer);
        if (menu.l3_visible) {
            menu.log("exp_l2: hide_l3");
            menu.hide_l3();
        }

        if (!menu.l2_visible || menu.l2_min) {
            $(".menu-l2").css("width","260px");
            $(".menu-text-l2").show();
            $(".sidebar-brand .brand-text").show();

            var ctrl = $("#menu-l2-controls");
            ctrl.html('<svg class="icon"><use xlink:href="#icon-contract"></use></svg>');
            //ctrl.attr("title",_Tr_Menu("Minimise sidebar")).removeClass("ctrl-hide").removeClass("ctrl-min").addClass("ctrl-exp");
        }

        // EnergyMon layout: set left margin for expanded sidebar
        $(".content-container").css("margin-left","260px");

        menu.l2_min = false;
        menu.l2_visible = true;
    },

    // L3 is disabled in EnergyMon layout
    show_l3: function () {
        menu.log("show_l3: disabled in EnergyMon layout");
    },

    hide_l3: function () {
        clearTimeout(menu.auto_hide_timer);
        if (menu.l3_visible) { 
            $(".menu-l3").css("left","0px");
            $(".menu-l3").css("width","0px");
        }
        menu.l3_visible = false;
    },

    disable_transition: function (element) {
        $(element).css("transition","none");
    },
    
    enable_transition: function (element) {
        setTimeout(function(){
            $(element).css("transition","all 0.3s ease-out");
        },0);  
    },

    resize: function() {
        menu.width = $(window).width();
        menu.height = $(window).height();

        if (!menu.is_disabled && menu.menu_top_visible) {
            if (menu.mode === "auto") {
                // Small screens: collapse sidebar
                if (menu.width < 576) {
                    menu.hide_l2();
                } else {
                    // Desktop/tablet: keep sidebar in current state (minimized or expanded)
                    // Don't force any particular state on resize
                    if (!menu.l2_visible) {
                        menu.min_l2();
                    }
                    menu.hide_l3();
                }
            }

            if (menu.width<576) {
                $(".menu-text-l1").hide();
            } else {
                $(".menu-text-l1").show();
            }
        }
    },
    
    // Currently only used by user login_block 
    disable: function() {
        menu.is_disabled = true;
        // Hide l2 immedietely without animation
        $(".menu-l2").hide();
        menu.hide_l1();
        menu.hide_l2(); // (auto hides l3)
    },

    // -----------------------------------------------------------------------
    // Menu events
    // -----------------------------------------------------------------------
    events: function() {

        // Hamburger menu toggle
        $("#hamburger-toggle").click(function(event){
            event.stopPropagation();
            menu.mode = 'manual';

            if (menu.l2_visible && !menu.l2_min) {
                // Currently expanded, minimize it
                menu.log("hamburger: min_l2");
                menu.min_l2();
            } else if (menu.l2_visible && menu.l2_min) {
                // Currently minimized, expand it
                menu.log("hamburger: exp_l2");
                menu.exp_l2();
            } else {
                // Currently hidden, show minimized
                menu.log("hamburger: min_l2");
                menu.min_l2();
            }
        });

        $(".menu-l1 li div").click(function(event){
            menu.last_active_l1 = menu.active_l1;
            menu.active_l1 = $(this).attr("l1");
            let item = menu.obj[menu.active_l1];
            // Remove active class from all menu items
            $(".menu-l1 li div").removeClass("active");
            $(".menu-l1 li div[l1="+menu.active_l1+"]").addClass("active");
            // If no sub menu then menu item is a direct link
            menu.mode = 'manual'
            if (item['l2']==undefined) {
                window.location = path+item['href']
            } else {
                if (menu.active_l1!=menu.last_active_l1) {
                    // new l1 menu clicked - redraw sidebar
                    menu.draw_l2();
                } else {
                    // same l1 menu clicked - toggle sidebar on mobile
                    if (menu.width < 576) {
                        if (menu.l2_visible) {
                            menu.hide_l2();
                        } else {
                            menu.exp_l2();
                        }
                    }
                }
                $(window).trigger('resize');
            }
        });

        $(".menu-l2").on("click","li div",function(event){
            let is_active = ($(this).attr("class") == "active" ? true : false);
            menu.active_l2 = $(this).attr("l2");
            let item = menu.obj[menu.active_l1]['l2'][menu.active_l2];
            // Remove active class from all menu items
            $(".menu-l2 li div").removeClass("active");
            // Set active class to current menu
            $(".menu-l2 li div[l2="+menu.active_l2+"]").addClass("active");
            // In EnergyMon layout, all L2 items are direct links (no L3)
        });

        $("#menu-l2-controls").click(function(event){
            event.stopPropagation();
            menu.mode = 'manual'
            menu.auto_hide = true;
            if (menu.l2_visible && menu.l2_min) {
                menu.log("menu-l2-controls: exp_l2");
                menu.exp_l2();
            } else {
                menu.log("menu-l2-controls: min_l2");
                menu.min_l2();
            }
            $(window).trigger('resize');
        });
        
        $(window).resize(function(){
            menu.resize();
        });
        
        $(window).scroll(function() {
          var scrollTop = $(window).scrollTop();
          var main = 0;
          if ((scrollTop > main) && menu.menu_top_visible && !menu.l2_visible && !menu.l3_visible) {
              menu.log("scroll: hide_menu_top");
              menu.hide_menu_top();
          } else if (scrollTop <= main && !menu.menu_top_visible) {
              menu.log("scroll: show_menu_top");
              menu.show_menu_top();
          }
        });

        // Manual dropdown toggle for user menu
        $(document).on('click', '#user-dropdown', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $parent = $(this).closest('.dropdown');
            var isOpen = $parent.hasClass('open');

            // Close all dropdowns first
            $('.dropdown').removeClass('open');

            // Toggle this dropdown
            if (!isOpen) {
                $parent.addClass('open');
            }
        });

        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.dropdown').length) {
                $('.dropdown').removeClass('open');
            }
        });

        // Prevent dropdown from closing when clicking inside it
        $(document).on('click', '.dropdown-menu', function(e) {
            e.stopPropagation();
        });
    },
    
    route: function(q) {
        var route = {
            controller: false,
            action: false,
            subaction: false
        }
        
        var q_parts = q.split("#");
        q_parts = q_parts[0].split("/");
        
        if (q_parts[0]!=undefined) route.controller = q_parts[0];
        if (q_parts[1]!=undefined) route.action = q_parts[0];
        if (q_parts[2]!=undefined) route.subaction = q_parts[0];
                
        return route
    },
    
    log: function(text) {
        var time = (new Date().getTime() - menu.init_time)*0.001;
        if (menu.debug) console.log(time.toFixed(3)+" "+text);
    }
};