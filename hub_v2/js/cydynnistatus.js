/*

Status page

*/

function cydynnistatus_update() {

  var tariff = {};

  $.ajax({                                      
      url: path+club+"/live",
      dataType: 'json',
      async: false,                      
      success: function(result) {
          live = result;
  }});
  
  tariff = live.tariff;

  var time = new Date();

  var hour = time.getHours();
  var minutes = time.getMinutes();

  $("#status-next").html("");
  
  if (tariff=="morning") $("#status-img").attr("src",path+"images/waiting-icon.png");
  if (tariff=="midday") $("#status-img").attr("src",path+"images/new-tick.png");
  if (tariff=="evening") $("#status-img").attr("src",path+"images/waiting-icon.png");
  if (tariff=="overnight") $("#status-img").attr("src",path+"images/new-tick.png");
  if (tariff=="generation") $("#status-img").attr("src",path+"images/new-tick.png");
  
  
  // If morning peak then wait until midday tariff
  if (tariff=="morning") {
      $("#status-pre").html(t("If possible"));
      $("#status-title").html(t("WAIT"));
      $("#tariff_summary").html(t("Now")+": "+t("Morning Price"));

      var time_to_wait = (11 - (hour+1))+" "+t("HOURS")+", "+(60-minutes)+" "+t("MINS");
      
      $("#status-until").html(t("until")+" <b>11<span style='font-size:12px'>AM</span></b> <span style='font-size:12px'>("+time_to_wait+" "+t("FROM NOW")+")</span><br>"+t("Why? cheaper around midday"));

      $("#tariff-now-title").html(t("MORNING<br>PRICE")).css("color","#ffb401");
      $("#tariff-now-circle").css("background-color","#ffb401");
      $("#tariff-now-price").html("12p");
  }

  // If evening peak then wait until overnight tariff
  if (tariff=="midday") {
      $("#status-pre").html(t("Now is a good time to use electricity"));
      $("#status-title").html(t("GO!"));
      $("#tariff_summary").html(t("Now")+": "+t("Midday Price"));

      var time_to_wait = (16 - (hour+1))+" "+t("HOURS")+", "+(60-minutes)+" "+t("MINS");
      $("#status-until").html(t("until")+" <b>4<span style='font-size:12px'>PM</span></b> <span style='font-size:12px'>("+time_to_wait+")</span><br>"+t("Why? midday price currently available"));
      
      $("#tariff-now-title").html(t("MIDDAY<br>PRICE")).css("color","#4dac34");
      $("#tariff-now-circle").css("background-color","#4dac34");
      $("#tariff-now-price").html("10p");
  }

  // If evening peak then wait until overnight tariff
  if (tariff=="evening") {
      $("#status-pre").html(t("If possible"));
      $("#status-title").html(t("WAIT"));
      $("#tariff_summary").html(t("Now")+": "+t("Evening Price"));

      var time_to_wait = (20 - (hour+1))+" "+t("HOURS")+", "+(60-minutes)+" "+t("MINS");
      $("#status-until").html(t("until")+" <b>8<span style='font-size:12px'>PM</span></b> <span style='font-size:12px'>("+time_to_wait+" "+t("FROM NOW")+")</span><br>"+t("Why? overnight price coming up"));

      $("#tariff-now-title").html(t("EVENING<br>PRICE")).css("color","#e6602b");
      $("#tariff-now-circle").css("background-color","#e6602b");
      $("#tariff-now-price").html("14p");
  }

  // If evening peak then wait until overnight tariff
  if (tariff=="overnight") {
      $("#status-pre").html(t("Now is a good time to use electricity"));
      $("#status-title").html(t("GO!"));

      $("#tariff_summary").html(t("Now")+": "+t("Overnight Price"));

      if (hour>6) {
          var time_to_wait = (24-(hour+1)+6)+" "+t("HOURS")+", "+(60-minutes)+" "+t("MINS");
      } else {
          var time_to_wait = (6-(hour+1))+" "+t("HOURS")+", "+(60-minutes)+" "+t("MINS");
      }
      $("#status-until").html(t("until")+" <b>6<span style='font-size:12px'>AM</span></b> <span style='font-size:12px'>("+time_to_wait+")</span><br>"+t("Why? overnight price currently available"));

      $("#tariff-now-title").html(t("OVERNIGHT<br>PRICE")).css("color","#014c2d");      
      $("#tariff-now-circle").css("background-color","#014c2d");
      $("#tariff-now-price").html("7.25p");
  }
  
  // If evening peak then wait until overnight tariff
  if (tariff=="generation") {
      $("#status-pre").html(t("Now is a good time to use electricity"));
      $("#status-title").html(t("GO!"));
      $("#tariff_summary").html(t("Now")+": "+t(ucfirst(club_settings.generator)+" Price"));
      $("#status-until").html(t("Why? Plenty of "+club_settings.generator+" currently available"));
      
      $("#tariff-now-title").html(t(club_settings.generator.toUpperCase()+"<br>PRICE")).css("color","#29aae3");
      $("#tariff-now-circle").css("background-color","#29aae3");
      $("#tariff-now-price").html("7p");
  }
  
  //$("#"+tariff+"-tariff-box").hide();

  //$(".tariff-img").hide();
 // $(".tariff-img[tariff="+tariff+"]").show();

  var levels = {
      bethesda: {high:50,medium:30,low:10},
      towerpower: {high:3,medium:1,low:0.5}
  }
 
  if (live.generation>=levels["bethesda"].high) {
      $("#generation-status").html(t("HIGH"));
  } else if (live.generation>=levels["bethesda"].medium) {
      $("#generation-status").html(t("MEDIUM"));
  } else if (live.generation>=levels["bethesda"].low) {
      $("#generation-status").html(t("LOW"));
  } else {
      $("#generation-status").html(t("VERY LOW"));
  }

  $("#generation-power").html(Math.round(live.generation));
}

