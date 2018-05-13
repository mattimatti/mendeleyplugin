var $ = require('jquery');

jQuery(document).ready(function() {

    var $input = $('#query');
    var $sorters = $('.sorter');
    var $sortfield = $('#sortfield');
    var $sortdirection = $('#sortdirection');

    // prevent submit
    $("#mendeleyform").submit(function(e) {
	e.preventDefault();
    });

    $sorters.prop('data-direction', '');

    // activate the sorter
    $("a[data-field='" + $sortfield.val() + "']").prop('data-direction', $sortdirection.val());

    var handleSorting = function() {

	var data = $(this).data();

	if (data.direction == '' || data.direction == 'desc') {
	    data.direction = 'asc';
	} else {
	    data.direction = 'desc';
	}

	console.debug('sorter', data);

	$('#sortfield').val(data.field);
	$('#sortdirection').val(data.direction);

	loadData();
    };

    /**
     * 
     */
    var loadTableData = function(params, callback) {
	$.ajax({
	    url : "/?mendeleysearch",
	    data : params,
	    type : "GET",
	    dataType : "json",
	    success : function(data) {
		console.debug('loadTableData::success', data);
		callback.apply(this, [ data ]);
	    },
	    error : function(xhr, status, message) {
		console.error(xhr.responseText);
		console.error("Sorry, there was a problem!: ", message);
	    },
	    complete : function(xhr, status) {
	    }
	});
    };

    /**
     * 
     */
    var loadFileInfoData = function(params, callback, $elm) {
	$.ajax({
	    url : "/?mendeleyview",
	    data : params,
	    type : "GET",
	    dataType : "json",
	    success : function(data) {
		console.debug('loadFileInfoData::success', data);
		callback.apply(this, [ data, $elm ]);
	    },
	    error : function(xhr, status, message) {
		console.error(xhr.responseText);
		console.error("Sorry, there was a problem!: ", message);
	    },
	    complete : function(xhr, status) {
	    }
	});
    };

    /**
     * Render data in the
     */
    var printFileInfo = function(data, $elm) {

	console.debug('printFileInfo', data, $elm);

	var html = '';
	var template = _.template($('#fileinfotemplate').html());

	var $container = $elm.find('.fileinfo');

	if (data.items.length > 1) {

	    $container.empty();

	    _.each(data.items, function(item) {
		html += template({
		    item : item
		});

		$container.append(html);

	    })

	} else {

	    var theFile = data.items[0];
	    console.debug(theFile);

	    window.location.href = "?mendeleydownload&id=" + theFile.id;

	}

    };

    /**
     * Render data in the table
     */
    var printData = function(data) {

	console.debug('printData', data);

	var $results = $('#results');

	var html = '';
	var template = _.template($('#rowtemplate').html());
	
	
	//console.debug(_.pluck(data.items, 'id'));
	
	var items = data.items;
	items = _.sortBy(items, data.params.sortfield);
	
	if(data.params.sortdirection === 'desc'){
	    items.reverse();
	}
	

	_.each(items, function(item) {
	    html += template({
		item : item
	    });
	})

	$results.empty().append(html);

	$results.find('.view').on('click', function(e) {
	    var $elm = $(e.currentTarget);
	    var params = $elm.data();
	    console.debug("clicked", e, params);
	    loadFileInfoData(params, printFileInfo, $elm.parent());
	});
    };

    /**
     * Prepare loading data
     */
    var loadData = function(e) {
	var params = $('#mendeleyform').serializeArray();

	if (params.query == '') {
	    alert('Missing Search Param');
	    return false;
	}

	loadTableData(params, printData);
    };

    /**
     * Utility function to delay execution
     */
    var delay = (function() {
	var timer = 0;
	return function(callback, ms, scope, arguments) {
	    clearTimeout(timer);
	    timer = setTimeout(function() {
		console.debug(callback, scope, arguments);
		callback.apply(scope, arguments);
	    }, ms);
	};
    })();

    /**
     * Debounced version to delay keyup trigger
     */
    var debounced = _.debounce(function(e) {
	loadData();
    }, 2000);

    $input.on('keyup', debounced);
    $sorters.click(handleSorting);

    // the
    $("#mendeleyform button").on('click', loadData);

});
