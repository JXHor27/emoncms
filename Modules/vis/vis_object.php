<?php

    /* Types:
      0 - feed realtime or daily
      1 - feed realtime
      2 - feed daily
      4 - boolean
      5 - text
      6 - float value
      7 - int value
      8 - multigraph id
      9 - colour
    */

    //CHAVEIRO TODO: only used in vis_main_view.php, should be php source for vis/widget/vis_render.js vis_widgetlist variable data

    /* fixed some typo, added space in between words*/

    $visualisations = array(
    
        'realtime' => array('label'=>ctx_tr('vis_messages','Real Time'), 'options'=>array(
            array('feedid',ctx_tr('vis_messages','feed'),1),
            array('colour',ctx_tr('vis_messages','colour'),9,'EDC240'),
            array('colourbg',ctx_tr('vis_messages','background colour'),9,'ffffff'),//renamed for clarity
            array('kw',ctx_tr('vis_messages','kW'),4,false),
            )
        ),
        
        // Hex colour EDC240 is the default color for flot. since we want existing setups to not change, we set the default value to it manually now,
        'rawdata'=> array('label'=>ctx_tr('vis_messages','Raw Data'), 'options'=>array(
            array('feedid',ctx_tr('vis_messages','feed'),1),
            array('fill',ctx_tr('vis_messages','fill'),7,0),
            array('colour',ctx_tr('vis_messages','colour'),9,'EDC240'),
            array('colourbg',ctx_tr('vis_messages','colourbg'),9,'ffffff'),
            array('units',ctx_tr('vis_messages','units'),5,''),
            array('dp',ctx_tr('vis_messages','dp'),7,'2'),
            array('scale',ctx_tr('vis_messages','scale'),6,'1'),
            array('average',ctx_tr('vis_messages','average'),4,'0'),
            array('delta',ctx_tr('vis_messages','delta'),4,'0'),
            array('skipmissing',ctx_tr('vis_messages','skipmissing'),4,'1')
            )
        ),
        
        'bargraph'=> array('label'=>ctx_tr('vis_messages','Bar Graph'), 'options'=>array(
            array('feedid',ctx_tr('vis_messages','feed'),0),
            array('colour',ctx_tr('vis_messages','colour'),9,'EDC240'),
            array('colourbg',ctx_tr('vis_messages','colourbg'),9,'ffffff'),
            array('interval',ctx_tr('vis_messages','interval'),7,'86400'),
            array('units',ctx_tr('vis_messages','units'),5,''),
            array('dp',ctx_tr('vis_messages','dp'),7,'1'),
            array('scale',ctx_tr('vis_messages','scale'),6,'1'),
            array('average',ctx_tr('vis_messages','average'),4,'0'),
            array('delta',ctx_tr('vis_messages','delta'),4,'0')
            )
        ),
        
        'zoom'=> array('label'=>ctx_tr('vis_messages','Zoom'), 'options'=>array(
            array('power',ctx_tr('vis_messages','power'),1),
            array('kwhd',ctx_tr('vis_messages','kwhd'),0),
            array('currency',ctx_tr('vis_messages','currency'),5,'&pound;'),
            array('currency_after_val',ctx_tr('vis_messages','currency_after_val'),7, 0),
            array('pricekwh',ctx_tr('vis_messages','pricekwh'),6,0.14),
            array('delta',ctx_tr('vis_messages','delta'),4,0)
        )),
        
        //rearranged and renamed stacked options for better clarity
        'stacked'=> array('label'=>ctx_tr('vis_messages','Stacked'), 'options'=>array(
            array('top',ctx_tr('vis_messages','Top Feed'),0),
            array('colourt',ctx_tr('vis_messages','Top Colour'),9,'7CC9FF'),
            array('bottom',ctx_tr('vis_messages','Bottom Feed'),0),
            array('colourb',ctx_tr('vis_messages','Bottom Feed'),9,'0096FF'),
            array('delta',ctx_tr('vis_messages','Delta'),4,0)
        )),
        
        'stackedsolar'=> array('label'=>ctx_tr('vis_messages','Stacked Solar'), 'options'=>array(
            array('solar',ctx_tr('vis_messages','solar'),0),
            array('consumption',ctx_tr('vis_messages','consumption'),0),
            array('delta',ctx_tr('vis_messages','delta'),4,0)
        )),
        
        'simplezoom'=> array('label'=>ctx_tr('vis_messages','Simple Zoom'), 'options'=>array(
            array('power',ctx_tr('vis_messages','power'),1),
            array('kwhd',ctx_tr('vis_messages','kwh'),0),
            array('delta',ctx_tr('vis_messages','delta'),4,0)
        )),
        
        'orderbars'=> array('label'=>ctx_tr('vis_messages','Order Bars'), 'options'=>array(
            array('feedid',ctx_tr('vis_messages','feed'),0),
            array('delta',ctx_tr('vis_messages','delta'),4,0)
        )),
        
        'multigraph' => array ('label'=>ctx_tr('vis_messages','Multigraph'), 'action'=>'multigraph', 'options'=>array(
            array('mid',ctx_tr('vis_messages','mid'),8)
        )),
        
        'editor'=> array('label'=>ctx_tr('vis_messages','Editor'), 'options'=>array(
            array('feedid',ctx_tr('vis_messages','feed'),1)
        )),
        
        // --------------------------------------------------------------------------------
        // Not currently available on emoncms.org
        // --------------------------------------------------------------------------------     
        'smoothie'=> array('label'=>ctx_tr('vis_messages','Smoothie'), 'options'=>array(
            array('feedid',ctx_tr('vis_messages','feed'),1),
            array('ufac',ctx_tr('vis_messages','ufac'),6))
        ),

        'compare' => array ('label'=>ctx_tr('vis_messages','Compare'), 'action'=>'compare', 'options'=>array(
            array('feedA',ctx_tr('vis_messages','Feed A'),1),
            array('feedB',ctx_tr('vis_messages','Feed B'),1)
        )),
        
        'timecompare'=> array('label'=>ctx_tr('vis_messages','Time Comparison'), 'options'=>array(
            array('feedid',ctx_tr('vis_messages','feed'),1),
            array('fill',ctx_tr('vis_messages','fill'),7,1),
            array('depth',ctx_tr('vis_messages','depth'),7,3),
            array('npoints',ctx_tr('vis_messages','data points'),7,800)
        )),
		
        // --------------------------------------------------------------------------------
        // psychrographic diagrams to appreciate summer confort
        // --------------------------------------------------------------------------------
        'psychrograph' => array ('label'=>ctx_tr('vis_messages','Psychrometric Diagram'), 'action'=>'psychrograph', 'options'=>array(
            array('mid',ctx_tr('vis_messages','mid'),8),
            array('hrtohabs',ctx_tr('vis_messages','% to abso.'),4, 1),
            array('givoni',ctx_tr('vis_messages','givoni style?'),4)
        ))
        // ---------------------------------------------------------------------------------      
    );
