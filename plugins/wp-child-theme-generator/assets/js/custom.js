( function( $ ) {

  $(document).ready(function($){
  	$('#custom-screenshot').hide();
    $('#custom-child').hide();
    $('#theme-info').show();
  $('#custom-select').on('change', function() {
       $var = $(this).val();

       $('#parent').val($var);
       $('#child-author').val($(this).find('option:selected').attr('data-author'));
       $('#child-name').val($(this).find('option:selected').attr('data-name') + ' Child');
       $('#child-description').val($(this).find('option:selected').attr('data-description'));
       $('#theme-info').hide();
       $('#custom-child').fadeIn('slow');
       
      });

  });
} )( jQuery );
