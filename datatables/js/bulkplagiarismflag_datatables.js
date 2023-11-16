/**
 * This defines all javascript needed in the coursework module.
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2011 University of London Computer Centre {@link http://ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


M.mod_coursework_datatables = {




    /**
     * This is to set up the listeners etc for the page elements on the allocations page.
     */
    init_plagiarism_flag_page: function (e, courseworkid, numberofmarkers, pagesize) {

        var options = {
            'courseworkid' :courseworkid,
            'group':0,
            'sortby':'',
            'sorthow':'',
            'perpage':10
        };
console.log('calling datatables');
        $('.plagiarism_flag').each(function() {
            if(jQuery().DataTable()) {
                console.log('Datatables loaded');
            } else {
                console.log('Datatables not loaded');
            }
            var numbersofcolumns =  $("table").find("th:first td").length;

            numberofmarkers= parseInt(numberofmarkers);
            var tableobject     = $(this).DataTable({
                "language": {
                    "search": "Search _INPUT_ use \"\" for exact word search"
                },
                "pageLength": pagesize

            });

            var tableelement    =   $(this);

            var wrapperelement = tableelement.parent('.dataTables_wrapper');
            var paginationelement = wrapperelement.find('.dataTables_paginate');

            // hide buttons
            wrapperelement.find('.dataTables_paginate, .dataTables_info, .dataTables_length, .dataTables_filter').css('visibility', 'hidden');
            wrapperelement.find('thead, .dt-button').each(function() {
                var me = $(this);
                me.css('pointer-events', 'none');
                if (me.hasClass('dt-button')) {
                    me.find('span').html(' ' + me.find('span').html());
                }
            });
            $('<div class="text-center pagination-loading"><i class="fa fa-spinner fa-spin"></i>loading</div>').insertAfter(paginationelement);
            $('<i class="fa fa-spinner fa-spin pagination-loading"></i>').insertBefore(wrapperelement.find('.dt-button > span'));


            $.ajax({
                url: '/mod/coursework/actions/ajax/datatable/bulkplagiarismflag.php',
                type: 'POST',
                data: options
            }).done(function(response) {

                var tablerows   =   "";
                //the table rows returned often have text spaces that jquery turns into objects
                //we dont like this so remove all elements in response of type text node
                 tablerows = $(response).filter(function() { return this.nodeType != Node.TEXT_NODE; });
                tableobject.rows.add($(tablerows)).draw(false);
                wrapperelement.find('.pagination-loading').remove();
                wrapperelement.find('thead, .dt-button').css('pointer-events', 'auto');
                wrapperelement.find('.dataTables_paginate, .dataTables_info, .dataTables_length, .dataTables_filter').css('visibility', 'visible');

            });

            //aatach all event handlers to elements in the datatable after the datatable has been drawn
            tableobject.on('draw',function (i,e) {


            })
console.log("this is a test");
            M.mod_coursework_datatables.init_allocation_checkboxes();
            M.mod_coursework_datatables.init_submission_button();



        });
    },

    init_allocation_checkboxes: function()  {
        //stop propagation of click event when select all asessor for column is clicked in header
        $('#selectall').click(function (e) {
            e.stopPropagation();
        });


        var datatable   =    $('#plagiarismflag_table').DataTable();


        $('#selectall').change(function () {
            var ischecked = $(this).is(":checked");
            if (ischecked) {

                datatable.$('.submission_checkbox').each(function (n, element) {
                    if ($(element).prop('disabled')== false) {
                        $(element).prop('checked', true);
                    }
                })
            } else {
                datatable.$('.submission_checkbox').prop('checked', false);
            }
        });




    },

    init_submission_button: function () {

var datatable   =    $('#plagiarismflag_table').DataTable();
        $('#selected_dates').click(function () {

            var submissionselected = false;

            var selectedsubmissions =   [];

console.log(datatable);
            datatable.$('.submission_checkbox').each(function (n, element) {



                if ($(element).is(":checked")) {
                    submissionselected = true;
                    selectedsubmissions.push($(element).val());
                }

            })

            if (submissionselected == true) {



                console.log('updating submissions');
                $.each(selectedsubmissions, function(i,subid){
                    $('<input />').attr('type', 'hidden')
                        .attr('name', 'submissionid_arr['+subid+']')
                        .attr('value', subid)
                        .appendTo('#coursework_plagiarism_flag_form');
                });
                console.log('added submissions');


                $('#coursework_plagiarism_flag_form').submit();
            } else {
                alert('You must make at least one selection');
            }


        });

    },


    init_sample_set_checkboxes: function (courseworkid) {

        // Unchecked 'Include in sample' checkbox disables
        // dropdown automatically.
        $('.sampling_set_checkbox').click(function (index,element) {

            var $checkbox = $(this);
            var $dropdown = $checkbox.nextAll('.assessor_id_dropdown');

            var $pinned = $checkbox.nextAll('.existing-assessor');
            var $child = $pinned.children('.pinned');

            var insampleset  =   ($checkbox.prop('checked') == true) ? 1 : 0;

            if ($dropdown.length) {
                if (insampleset) {
                    $dropdown.prop("disabled", false);
                    $child.prop("disabled", false);

                } else {
                    $dropdown.val('');
                    $dropdown.prop("disabled", true);
                    $child.prop("disabled", true);
                    $("#same_assessor").remove();

                    $(this).siblings().each(function(index,element)  {
                       if ($(this).hasClass('existing-assessor')) {
                           $(this).remove();
                       }
                    });
                }

                allocstablesampledata  =   {

                    'allocatableinfo': $(this).prop('name').replaceAll('[',':').replaceAll(']',''),
                    'courseworkid': courseworkid,
                    'insample': insampleset
                }


                $.ajax({
                    url: '/mod/coursework/actions/update_allocatable_in_sample.php',
                    type: 'POST',
                    data: allocstablesampledata
                }).done(function (response) {

                });
            }
        });


        $('.sampling_set_checkbox').each(function () {

            var $checkbox = $(this);

            var $assessddname = $checkbox.attr('id').replace('_samplecheckbox', '');

            var $assessdd = $('#' + $assessddname);

            if ($checkbox.is(":checked")) {
                $assessdd.prop("disabled", false)
            } else {
                $assessdd.prop("disabled", true);
            }

        });


    }
}
