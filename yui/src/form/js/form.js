// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * JavaScript for the pathway availability condition form.
 *
 * @module     moodle-availability_pathway-form
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
M.availability_pathway = M.availability_pathway || {}; // eslint-disable-line camelcase

/**
 * @class M.availability_pathway.form
 * @extends M.core_availability.plugin
 */
M.availability_pathway.form = Y.Object(M.core_availability.plugin);

/**
 * Pathway activities in this course, each with an options array.
 *
 * @property cms
 * @type Array
 */
M.availability_pathway.form.cms = null;

/**
 * Initialises this plugin.
 *
 * @method initInner
 * @param {Array} cms Array of objects with id, name and options
 */
M.availability_pathway.form.initInner = function(cms) {
    this.cms = cms;
};

/**
 * Builds the form node for one condition.
 *
 * @method getNode
 * @param {Object} json Saved condition data
 * @return {Y.Node} The form node
 */
M.availability_pathway.form.getNode = function(json) {
    var html;
    var node;
    var cmField;
    var optionField;
    var self = this;

    html = '<label class="p-r-1">' + M.util.get_string('label_cm', 'availability_pathway') + ' ';
    html += '<span class="availability-group"><select name="cm" class="custom-select">';
    html += '<option value="0">' + M.util.get_string('choosedots', 'moodle') + '</option>';
    Y.Array.each(this.cms, function(cm) {
        html += '<option value="' + cm.id + '">' + Y.Escape.html(cm.name) + '</option>';
    });
    html += '</select></span></label> ';
    html += '<label>' + M.util.get_string('label_option', 'availability_pathway') + ' ';
    html += '<span class="availability-group"><select name="option" class="custom-select"></select></span></label>';

    node = Y.Node.create('<span class="form-inline">' + html + '</span>');

    cmField = node.one('select[name=cm]');
    optionField = node.one('select[name=option]');

    /**
     * Repopulates the option menu for the currently selected activity.
     *
     * @param {Number} selected Option id to preselect
     */
    var refreshOptions = function(selected) {
        var cmid = parseInt(cmField.get('value'), 10),
            markup = '<option value="0">' +
                Y.Escape.html(M.util.get_string('anyoption', 'availability_pathway')) + '</option>';

        Y.Array.each(self.cms, function(cm) {
            if (cm.id === cmid) {
                Y.Array.each(cm.options, function(option) {
                    markup += '<option value="' + option.id + '">' + Y.Escape.html(option.name) + '</option>';
                });
            }
        });

        optionField.setHTML(markup);
        if (selected) {
            optionField.set('value', '' + selected);
        }
    };

    if (json.cm !== undefined) {
        cmField.set('value', '' + json.cm);
    }
    refreshOptions(json.option !== undefined ? json.option : 0);

    cmField.on('change', function() {
        refreshOptions(0);
        M.core_availability.form.update();
    });
    optionField.on('change', function() {
        M.core_availability.form.update();
    });

    return node;
};

/**
 * Reads values out of the form node.
 *
 * @method fillValue
 * @param {Object} value Object to fill
 * @param {Y.Node} node The form node
 */
M.availability_pathway.form.fillValue = function(value, node) {
    value.cm = parseInt(node.one('select[name=cm]').get('value'), 10);
    value.option = parseInt(node.one('select[name=option]').get('value'), 10);
    if (isNaN(value.option)) {
        value.option = 0;
    }
};

/**
 * Reports validation errors.
 *
 * @method fillErrors
 * @param {Array} errors Array of error strings
 * @param {Y.Node} node The form node
 */
M.availability_pathway.form.fillErrors = function(errors, node) {
    var value = {};
    this.fillValue(value, node);

    if (!value.cm) {
        errors.push('availability_pathway:error_selectcm');
    }
};
