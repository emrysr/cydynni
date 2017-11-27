/*

Club page

*/

var start = 0;
var end = 0;
var apikey = "";
var units = "kW";

var club_data = [];
var exported_hydro_data = [];
var used_hydro_data = [];
var clubseries = [];

var club_pie1_data = [];
var club_pie2_data = [];
var club_pie3_data_cost = [];
var club_pie3_data_energy = [];

var club_score = -1;
var club_hydro_use = 0;
var club_view = "bargraph";
var club_height = 0;

// Initial view range 24 hours
view.end = +new Date;
view.start = view.end - (3600000*24.0*6);

function club_summary_load()
{
  $.ajax({                                      
      url: path+"club/summary/day",
      dataType: 'json',                  
      success: function(result) {
          
          if (result!="Invalid data") {
          
              var score = Math.round(100*((result.kwh.overnight + result.kwh.midday + result.kwh.hydro) / result.kwh.total));
              
              if (result.dayoffset==1) {
                  $("#club_score_text").html(t("Yesterday we scored"));
              } else {
                  if (result.month==undefined) {
                      $("#club_score_text").html(t("We scored"));
                  } else {
                      $("#club_score_text").html(t("We scored")+" "+t("on")+" "+t(result.month)+" "+result.day);
                  }
              }
              
              $("#club_score").html(score);
              if (score>20) $("#club_star1").attr("src","images/staryellow.png");
              if (score>40) setTimeout(function() { $("#club_star2").attr("src","images/staryellow.png"); }, 100);
              if (score>60) setTimeout(function() { $("#club_star3").attr("src","images/staryellow.png"); }, 200);
              if (score>80) setTimeout(function() { $("#club_star4").attr("src","images/staryellow.png"); }, 300);
              if (score>90) setTimeout(function() { $("#club_star5").attr("src","images/staryellow.png"); }, 400);
              
              setTimeout(function() {
                  if (score<30) {
                      $("#club_statusmsg").html(t("We are using power in a very expensive way"));
                  }
                  if (score>=30 && score<70) {
                      $("#club_statusmsg").html(t("We could do more to make the most of the hydro power and power at cheaper times of day. Can we move more electricity use away from peak times?"));
                  }
                  if (score>=70) {
                      $("#club_statusmsg").html(t("We’re doing really well using the hydro and cheaper power"));
                  }
                  //club_resize();
              }, 400);
              
              // Hydro value retained in the club
              var hydro_value = result.kwh.hydro * 0.07;

              var ext = "";
              if (result.day==1) ext = "st";
              if (result.day==2) ext = "nd";
              if (result.day==3) ext = "rd";
              if (result.day>3) ext = "th";
              if (lang=="cy") ext = "";

              $(".club_date").html(result.day+t(ext)+" "+t(result.month));
              
              // 2nd ssection showing total consumption and cost
              $(".club_hydro_value").html("£"+(hydro_value).toFixed(2));
              $("#club_value_summary").html("£"+(hydro_value).toFixed(2)+" "+t("kept in the club"));
              
              // Club pie chart
              club_pie1_data = [
                {name:t("MORNING"), value: result.kwh.morning, color:"#ffdc00"},
                {name:t("MIDDAY"), value: result.kwh.midday, color:"#4abd3e"},
                {name:t("EVENING"), value: result.kwh.evening, color:"#c92760"},
                {name:t("OVERNIGHT"), value: result.kwh.overnight, color:"#274e3f"},
                {name:t("HYDRO"), value: result.kwh.hydro, color:"#29aae3"} 
              ];

              // Club pie chart
              club_pie2_data = [
                {name:t("MORNING"), value: result.kwh.morning, color:"#ffdc00"},
                {name:t("MIDDAY"), value: result.kwh.midday, color:"#4abd3e"},
                {name:t("EVENING"), value: result.kwh.evening, color:"#c92760"},
                {name:t("OVERNIGHT"), value: result.kwh.overnight, color:"#274e3f"} 
              ];
              
              // club pie chart
              club_pie3_data_cost = [
                {name:t("MORNING"), hydro: result.hydro.morning*0.07, import: result.kwh.morning*0.12, color:"#ffdc00"},
                {name:t("MIDDAY"), hydro: result.hydro.midday*0.07, import: result.kwh.midday*0.10, color:"#4abd3e"},
                {name:t("EVENING"), hydro: result.hydro.evening*0.07, import: result.kwh.evening*0.14, color:"#c92760"},
                {name:t("OVERNIGHT"), hydro: result.hydro.overnight*0.07, import: result.kwh.overnight*0.0725, color:"#274e3f"} 
              ];
              
              // household pie chart
              club_pie3_data_energy = [
                {name:t("MORNING"), hydro: result.hydro.morning, import: result.kwh.morning, color:"#ffdc00"},
                {name:t("MIDDAY"), hydro: result.hydro.midday, import: result.kwh.midday, color:"#4abd3e"},
                {name:t("EVENING"), hydro: result.hydro.evening, import: result.kwh.evening, color:"#c92760"},
                {name:t("OVERNIGHT"), hydro: result.hydro.overnight, import: result.kwh.overnight, color:"#274e3f"} 
              ];
              
              $("#club_hydro_kwh").html(result.kwh.hydro);
              $("#club_morning_kwh").html(result.kwh.morning);
              $("#club_midday_kwh").html(result.kwh.midday);
              $("#club_evening_kwh").html(result.kwh.evening);
              $("#club_overnight_kwh").html(result.kwh.overnight);

              $("#club_hydro_cost").html((result.kwh.hydro*0.07).toFixed(2));
              $("#club_morning_cost").html((result.kwh.morning*0.12).toFixed(2));
              $("#club_midday_cost").html((result.kwh.midday*0.10).toFixed(2));
              $("#club_evening_cost").html((result.kwh.evening*0.14).toFixed(2));
              $("#club_overnight_cost").html((result.kwh.overnight*0.0725).toFixed(2));
                                         
              club_hydro_use = result.kwh.hydro
              
              club_pie_draw();
          } 
          else
          {
          
          }
      } 
  });
}

function club_pie_draw() {

    //var width = $("#piegraph_bound").width();
    //var height = $("#piegraph_bound").height();
    //if (width>400) width = 400;
    //var height = width*0.9;
    
    width = 300;
    height = 300;

    $("#club_piegraph1_placeholder").attr('width',width);
    $("#club_piegraph2_placeholder").attr('width',width);
    $('#club_piegraph1_placeholder').attr("height",height);
    $('#club_piegraph2_placeholder').attr("height",height);
    
    //$("#hydro_droplet_placeholder").attr('width',width);
    //$('#hydro_droplet_bound').attr("height",height);
    //$('#hydro_droplet_placeholder').attr("height",height);
    
    var options = {
      color: "#3b6358",
      centertext: "THIS WEEK",
      width: width,
      height: height
    };
    
    // piegraph1("club_piegraph1_placeholder",club_pie1_data,options); 
    piegraph3("club_piegraph1_placeholder",club_pie3_data_energy,options); 
    
    // piegraph2("club_piegraph2_placeholder",club_pie2_data,club_hydro_use,options);
    piegraph3("club_piegraph2_placeholder",club_pie3_data_cost,options);
     
    // Hydro droplet
    // hydrodroplet("hydro_droplet_placeholder",(club_hydro_use*1).toFixed(1),{width: width,height: height});
    
}


function club_bargraph_load() {

    var npoints = 200;
    interval = ((view.end - view.start) * 0.001) / npoints;
    interval = round_interval(interval);
    
    // Limit interval to 1800s
    if (interval<1800) interval = 1800;
    var intervalms = interval * 1000;
    
    // Start and end time rounding
    view.end = Math.floor(view.end / intervalms) * intervalms;
    view.start = Math.floor(view.start / intervalms) * intervalms;

    // Load data from server
    var hydro_data = feed.getaverage(1,view.start,view.end,interval,1,1);
    var club_data = feed.getaverage(2,view.start,view.end,interval,1,1);
    
    // -------------------------------------------------------------------------
    // Colour code graph
    // -------------------------------------------------------------------------

    // kWh scale
    var scale = 1;
    if (units=="kWh") scale = (interval / 1800);
    if (units=="kW") scale = 2;

    var morning_data = [];
    var midday_data = [];
    var evening_data = [];
    var overnight_data = [];
    exported_hydro_data = [];
    used_hydro_data = [];
    
    var total_hydro = 0;
    var total_used_hydro = 0;
    var total_club = 0;
    var total_time = 0;

    for (var z in club_data) {    
        var time = club_data[z][0];    
        var d = new Date(time);
        var hour = d.getHours();
        
        var hydro = hydro_data[z][1] * scale;
        var club = club_data[z][1] * scale;
        
        var overnight = 0;
        var morning = 0;
        var midday = 0;
        var evening = 0;
        var exported_hydro = 0;
        var used_hydro = 0;

        // When available hydro is more than club consumption
        if (hydro>club) {
            // Hydro export
            exported_hydro = hydro - club;
            // Hydro used
            used_hydro = club;
            // No imported power at tariff periods:

        } else {
            // Hydro used
            used_hydro = hydro;
            // Grid import
            var grid_import = club - hydro;
            // Import times
            if (hour<6) overnight = grid_import;
            if (hour>=6 && hour<11) morning = grid_import;
            if (hour>=11 && hour<16) midday = grid_import;
            if (hour>=16 && hour<20) evening = grid_import;
            if (hour>=20) overnight = grid_import;
        }

        overnight_data[z] = [time,overnight];
        morning_data[z] = [time,morning];
        midday_data[z] = [time,midday];
        evening_data[z] = [time,evening];
        exported_hydro_data[z] = [time,exported_hydro];
        used_hydro_data[z] = [time,used_hydro];
        
        if (units=="kW") {
            total_hydro += hydro * (interval/3600);
            total_club += club * (interval/3600);
            total_used_hydro += used_hydro * (interval/3600);
        } else {
            total_hydro += hydro;
            total_club += club;
            total_used_hydro += used_hydro;
        }
        total_time += interval;
    }    
    
    // ----------------------------------------------------------------------------
    // estimate
    // ----------------------------------------------------------------------------
    hydro_estimate = [];
    club_estimate = [];
    
    var lasttime = 0;
    var lastvalue = 0;
    for (var z in hydro_data) {
        if (hydro_data[z][1]!=null) {
            lasttime = hydro_data[z][0];
            lastvalue = hydro_data[z][1];
        } 
    }
    
    if ((((new Date()).getTime()-view.end)<3600*1000*48) && ((view.end-lasttime)*0.001)>1800) {
        // ----------------------------------------------------------------------------
        // HYDRO estimate USING YNNI PADARN PERIS DATA
        // ----------------------------------------------------------------------------
        $.ajax({                                      
            url: path+"hydro/estimate?start="+view.start+"&end="+view.end+"&interval="+interval+"&lasttime="+lasttime+"&lastvalue="+lastvalue,
            dataType: 'json', async: false, success: function(result) {
            hydro_estimate = result;
            
            for (var z in hydro_estimate) {
                hydro_estimate[z][1] = hydro_estimate[z][1] * scale;
            }
        }});
        
        // ----------------------------------------------------------------------------
        // CONSUMPTION estimate
        // ----------------------------------------------------------------------------
        var d1 = new Date();
        var t1 = d1.getTime()*0.001;
        if (view.end>0) t1 = view.end * 0.001;
        var d3 = new Date(lasttime);
        var t3 = d3.getTime()*0.001;
        var divisions_behind = Math.floor((t1 - t3) / interval);
        
        var club_estimate_raw = [];
        
        var time = hydro_estimate[0][0];
        
        $.ajax({                                      
            url: path+"club/estimate?lasttime="+lasttime+"&interval="+interval,
            dataType: 'json',
            async: false,                      
            success: function(result) {
                var club_estimate_raw = result;
                var l = club_estimate_raw.length;
                
                club_estimate = [];
                for (var h=0; h<divisions_behind; h++) {
                    club_estimate.push([time+(h*interval*1000),club_estimate_raw[h%l]*scale]);
                }
        }});
       
    }
    // ----------------------------------------------------------------------------
    
    clubseries = [];
    
    var widthprc = 0.75;
    var barwidth = widthprc*interval*1000;
    
    // Actual
    clubseries.push({
        stack: true, data: used_hydro_data, color: "#29aae3", label: t("Used Hydro"),
        bars: { show: true, align: "center", barWidth: barwidth, fill: 1.0, lineWidth:0}
    });
    clubseries.push({
        stack: true, data: overnight_data, color: "#014c2d", label: t("Overnight Tariff"),
        bars: { show: true, align: "center", barWidth: barwidth, fill: 1.0, lineWidth:0}
    });
    clubseries.push({
        stack: true, data: morning_data, color: "#ffb401", label: t("Morning Tariff"),
        bars: { show: true, align: "center", barWidth: barwidth, fill: 1.0, lineWidth:0}
    });
    clubseries.push({
        stack: true, data: midday_data, color: "#4dac34", label: t("Midday Tariff"),
        bars: { show: true, align: "center", barWidth: barwidth, fill: 1.0, lineWidth:0}
    });
    clubseries.push({
        stack: true, data: evening_data, color: "#e6602b", label: t("Evening Tariff"),
        bars: { show: true, align: "center", barWidth: barwidth, fill: 1.0, lineWidth:0}
    });
    clubseries.push({
        stack: true, data: exported_hydro_data, color: "#a5e7ff", label: t("Exported Hydro"),
        bars: { show: true, align: "center", barWidth: barwidth, fill: 1.0, lineWidth:0}
    });

    // estimate
    clubseries.push({
        data: hydro_estimate, color: "#dadada", label: t("Hydro estimate"),
        bars: { show: true, align: "center", barWidth: barwidth, fill: 1.0, lineWidth:0}
    });
    clubseries.push({
        data: club_estimate, color: "#aaa", label: t("Club estimate"),
        bars: { show: true, align: "center", barWidth: barwidth, fill: 0.4, lineWidth:0}
    });
    
    // club_bargraph_draw();
}

function club_bargraph_resize() {

    var window_width = $(window).width();
    flot_font_size = 12;
    if (window_width<450) flot_font_size = 10;

    width = $("#club_bargraph_bound").width();
    
    var h = 400; if (width<400) h = width;
    
    $("#club_bargraph_placeholder").width(width);
    $('#club_bargraph_bound').height(h);
    $('#club_bargraph_placeholder').height(h);
    height = h;
    club_bargraph_draw();
}

function club_bargraph_draw() {

    var options = {
        legend: { show: true, noColumns: 8, container: $('#legendholder') },
        xaxis: { 
            mode: "time", 
            timezone: "browser", 
            font: {size:flot_font_size, color:"#666"}, 
            // labelHeight:-5
            reserveSpace:false,
            min: view.start,
            max: view.end
        },
        yaxis: { 
            font: {size:flot_font_size, color:"#666"}, 
            // labelWidth:-5
            reserveSpace:false,
            min:0
        },
        selection: { mode: "x" },
        grid: {
            show:true, 
            color:"#aaa",
            borderWidth:0,
            hoverable: true, 
            clickable: true
        }
    }
    
    if (units=="kW") options.yaxis.max = 100;
    
    if ($("#club_bargraph_placeholder").width()>0) {
        $.plot("#club_bargraph_placeholder",clubseries, options);
    }
}

function round_interval(interval) {
    var outinterval = 1800;
    if (interval>3600*1) outinterval = 3600*1;
    
    if (interval>3600*2) outinterval = 3600*2;
    if (interval>3600*3) outinterval = 3600*3;
    if (interval>3600*4) outinterval = 3600*4;
    if (interval>3600*5) outinterval = 3600*5;
    if (interval>3600*6) outinterval = 3600*6;
    if (interval>3600*12) outinterval = 3600*12;
    
    if (interval>3600*24) outinterval = 3600*24;
    
    if (interval>3600*36) outinterval = 3600*36;
    if (interval>3600*48) outinterval = 3600*48;
    if (interval>3600*72) outinterval = 3600*72;

    return outinterval;
}

$(".club-left").click(function(event) {
    event.stopPropagation();
    var time_window = view.end - view.start;
    view.end -= time_window * 0.5;
    view.start -= time_window * 0.5;
    club_bargraph_load();
    club_bargraph_draw();
});

$(".club-right").click(function(event) {
    event.stopPropagation();
    var time_window = view.end - view.start;
    view.end += time_window * 0.5;
    view.start += time_window * 0.5;
    club_bargraph_load();
    club_bargraph_draw();
});

$(".club-day").click(function(event) {
    event.stopPropagation();
    end = 0;
    start = 0;
    club_bargraph_load();
    club_bargraph_draw();
});

$(".club-week").click(function(event) {
    event.stopPropagation();
    view.end = +new Date;
    view.start = view.end - (3600000*24.0*7);
    club_bargraph_load();
    club_bargraph_draw();
});

$(".club-month").click(function(event) {
    event.stopPropagation();
    view.end = +new Date;
    view.start = view.end - (3600000*24.0*30);
    club_bargraph_load();
    club_bargraph_draw();
});

$(".club-year").click(function(event) {
    event.stopPropagation();
    view.end = +new Date;
    view.start = view.end - (3600000*24.0*365);
    club_bargraph_load();
    club_bargraph_draw();
});

$('#club_bargraph_placeholder').bind("plotselected", function (event, ranges) {
    view.start = ranges.xaxis.from;
    view.end = ranges.xaxis.to;
    club_bargraph_load();
    club_bargraph_draw();
});

$('#club_bargraph_placeholder').bind("plothover", function (event, pos, item) {

    if (item) {
        var z = item.dataIndex;
        var selected_series = clubseries[item.seriesIndex].label;
        
        if (previousPoint != item.datapoint) {
            previousPoint = item.datapoint;

            $("#tooltip").remove();
            
            // Date and time
            var itemTime = item.datapoint[0];
            var d = new Date(itemTime);
            var days = ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"];
            var months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
            var mins = d.getMinutes();
            if (mins==0) mins = "00";
            var date = d.getHours()+":"+mins+" "+days[d.getDay()]+", "+months[d.getMonth()]+" "+d.getDate();
            
            var out = date+"<br>";
                        
            // Non estimate part of the graph
            if (selected_series!=t("Hydro estimate") && selected_series!=t("Club estimate")) {

                // Draw non estimate tooltip
                var total_consumption = 0;
                for (var i in clubseries) {
                    var series = clubseries[i];
                    // Only show tooltip item if defined and more than zero
                    if (series.data[z]!=undefined && series.data[z][1]>0) {
                        if (series.label!=t("Hydro estimate") && series.label!=t("Club estimate")) {
                            out += series.label+ ": "+(series.data[z][1]*1).toFixed(1)+units+"<br>";
                            if (series.label!=t("Exported Hydro")) total_consumption += series.data[z][1]*1;
                        }
                    }
                }
                if (total_consumption) out += t("Total consumption: ")+(total_consumption).toFixed(1)+units;
            
            } else {
                // Print estimate amounts
                out += clubseries[6].label+ ": "+(clubseries[6].data[z][1]*1).toFixed(1)+units+"<br>";
                out += clubseries[7].label+ ": "+(clubseries[7].data[z][1]*1).toFixed(1)+units+"<br>";
            }
            tooltip(item.pageX,item.pageY,out,"#fff");
        }
    } else $("#tooltip").remove();
});