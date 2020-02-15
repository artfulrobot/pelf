(function(angular, $, _) {
class ObjectWithOrderedKeys {
  constructor() {
    this.list = [];
    this.dataObj = {};
  }

  has(prop) {
    return prop in this.dataObj;
  }

  getProp(prop) {
    return this.dataObj[prop];
  }
  /**
   * Get item by its order. 0 is first. -1 for last etc.
   *
   */
  getItem(i) {
    if (i < 0) {
      i = this.list.length + i;
    }
    return this.dataObj[this.list[i]];
  }

  setProp(prop, value) {
    if (!this.has(prop)) {
      this.list.push(prop);
    }
    this.dataObj[prop] = value;
  }

  deleteProp(prop) {
    if (this.has(prop)) {
      //this.list = this.list.filter(p => { console.log({p, prop, cmp: p!=prop}); return p != prop; });
      this.list = this.list.filter(p => p != prop );
      delete this[prop];
    }
  }

  forEach(fn) {
    return this.list.forEach(prop => fn(this.dataObj[prop], prop));
  }

  count() {
    return this.list.length;
  }

  keys() {
    // Returns a copy of the keys.
    return this.list.map(prop => prop);
  }

  map(fn) {
    return this.list.map(prop => fn(this.dataObj[prop], prop));
  }

  // The fn takes the original value and key
  // and must return an object with k and v as keys.
  mapToNew(fn) {
    var o = new ObjectWithOrderedKeys();
    this.list.map(prop => {
      var { k, v } = fn(this.dataObj[prop], prop);
      o.setProp(k, v);
    });
    return o;
  }

  values() {
    return this.map(x => x);
  }

  filter(fn) {
    this.list.forEach(prop => {
      if (!fn(this.dataObj[prop], prop)) { this.deleteProp(prop); }
    });
  }

  // iteree takes the value and the key and returns the value used for the sort
  sortBy(iteree) {
    if (typeof iteree === 'string') {
      const prop = iteree;
      iteree = v => v[prop];
    }

    this.list.sort((a, b) => {
      const aV = iteree(this.dataObj[a], a);
      const bV = iteree(this.dataObj[b], b);
      if (aV < bV) { return -1; }
      else if (aV > bV) { return 1; }
      else return 0;
    });
  }

  toArray() {
    return this.map((value, key) => [{key, value}]);
  }

  log() {
    this.list.forEach(prop => console.log(`${prop}: ${JSON.stringify(this.dataObj[prop])}`));
  }

}

/**
 * This holds a group and its items. It is used by Pivot and is not made for general use outside of that.
 *
 * e.g.  Data:
 * [
 *   { paymentType: "Donation",  campaign: "End Coal",           amount: 1000 },
 *   { paymentType: "Donation",  campaign: "End Coal",           amount: 2000 },
 *   { paymentType: "Donation",  campaign: "Community Building", amount: 4000 },
 *   { paymentType: "Event Fee", campaign: "Community Building", amount: 8000 },
 * ]
 *
 * You may define groups: paymentType, campaign.
 *
 * The top level GroupedData object would represent all rows.
 * - It would have childGroups "Donation" and "Event Fee".
 * - It would have depth 0
 * - It would have a reference to all the groupDefs, and (from this and depth) it knows which groupDef it represents.
 *
 * The childGroup "Donation" would be another GroupedData object which:
 * - has childGroups "End Coal" and "Community Building".
 * - depth 1
 * - has the 2nd group def.
 *
 * The child group "End Coal" would be another GroupedData object:
 * - depth 2
 * - has no groupDef.
 * - has no childGroups and therefore is a holder of the row(s) of data that match that group.
 *
 * GroupedData objects are used to group and index the data for cells, row headers and column headers.
 */
class GroupedData {
  constructor(groupDefs, groupKey,  parentGroup) {
    // But we can set our groupDef now.
    this.depth = parentGroup ? parentGroup.depth + 1 : 0;
    this.groupDefs = groupDefs;
    this.groupDef = this.groupDefs[this.depth];
    this.groupKey = groupKey;
    this.parentGroup = parentGroup ?? null;
    this.childGroups = new ObjectWithOrderedKeys();
    this.rows = [];
  }
  addNestedRow(keys, row) {
    if (keys.length > 0) {
      // We don't store the row yet, it's in a subgroup below this group.

      const groupKey = keys[0];
      var subGroup;
      if (!this.childGroups.has(groupKey)) {
        // this group has not been created yet.
        subGroup = new GroupedData(this.groupDefs, groupKey, this);
        this.childGroups.setProp(groupKey, subGroup);
      }
      else {
        subGroup = this.childGroups.getProp(groupKey);
      }
      subGroup.addNestedRow(keys.slice(1), row);
    }
    else {
      // No more subgroups, this row belongs to us.
      this.rows.push(row);
    }
  }
  getNested(keys, rows) {
    rows = rows || [];
    if (keys.length > 0) {
      // We don't store the row yet, it's in a subgroup below this group.
      const groupKey = keys[0];
      if (!this.childGroups.has(groupKey)) {
        // this group does not exist.
        return '';
      }
      else {
        return this.childGroups.getProp(groupKey).getNested(keys.slice(1));
      }
    }
    else {
      // No more subgroups, this row belongs to us.
      return this.rows;
    }
  }
  getAllRows() {
    var rows = [];
    if (this.childGroups.count()) {
      this.childGroups.forEach( subgroup => {
        rows = rows.concat(subgroup.getAllRows());
      });
      return rows;
    }
    else {
      return this.rows;
    }
  }
  /**
   * Returns an object representing a row or a column, with metadata
   * about that group and the header cells.
   *
   * {
   *   type: 'data',
   *   ancestry: [groupKey, groupKey...],
   *   cells: [ {obj}, ... ]
   * }
   *
   */
  getAllGroupKeysFlattened(mode, items, group) {
    mode = mode || 'rows';
    if (!group) {
      // Top level group.
      group = {ancestry: [], cells: []};
      items = [];
    }

    if (this.childGroups.count()) {
      // We have not yet processed all our groups.
      // Note the first level of childGroups are the first actual rows/cols.
      this.childGroups.forEach( subgroup => {
        group.ancestry.push(subgroup.groupKey);
        const cell = {groupKey: subgroup.groupKey, span:1, groupDef: this.groupDef };
        cell.formatted = cell.groupDef.formatter(cell);
        group.cells.push(cell);
        var l = items.length;
        // recurse into the next level of group. This will duplicate our exiting cells.
        subgroup.getAllGroupKeysFlattened(mode, items, group);
        if (items.length - l > 1) {
          // This item spans multiple cells.
          // Set the span of the first item to the span:
          items[l].cells[this.depth].span = items.length - l;
          //console.log(`at depth ${this.depth}, item: ${items[l].cells.map(c => c.ancestry.join(','))} span: ${items.length - l}`);
          // Erase others.
          for (var i=l+1; i<items.length; i++) {
            if (mode === 'rows') {
              items[i].cells.splice([this.depth], 1);
            }
            else if (mode === 'columns') {
              delete items[i].cells[this.depth];
            }
          }
        }
        // By the time we are here, our group and all its children have been added to items.
        // So we now clean up a bit.
        group.ancestry.pop();
        group.cells.pop();
      });
      // We've completed a row/column group. Should we insert a total row/col?
      if (this.groupDef.total) {
        var n = this.groupDefs.length - group.cells.length;
        // We need cells to be padded to the length of the number of group defs
        group.cells.push({formatted: 'Total'}); // @todo use span instead @todo ts()
        for (var i=n-1;i>0;i--) { group.cells.push({formatted: ''}); }
        group.isTotal = true;
        // Create row.
        items.push(this.copyGroup(group));
        // strip out stuff we added.
        for (var i=n;i>0;i--) { group.cells.pop(); }
        delete group.isTotal;
      }
    }
    else {
      // We're the last group, so the last header.
      items.push(this.copyGroup(group));
      //console.warn("Added item" + JSON.stringify(items));
    }
    return items;
  }
  copyGroup(group) {
    const copy = {
      cells: group.cells.map(cell => Object.assign({}, cell)),
      ancestry: group.ancestry.slice(),
    };
    if (group.isTotal) { copy.isTotal = true; }
    return copy;
  }
  sort() {
    // We can only operate if we have data (and a groupDef)
    if (this.childGroups.count()) {
      this.childGroups.sortBy(this.groupDef.sortAccessor);
      // Recurse into subgroups.
      this.childGroups.forEach( subgroup => {
        subgroup.sort();
      });
    }
  }
}

class Pivot {
  constructor(options) {
    this.rowGroupDefs = this.checkGroupDefs(options.rowGroupDefs);
    this.colGroupDefs = this.checkGroupDefs(options.colGroupDefs);
    this.valueAccessor = options.valueAccessor;
    this.valueFormatter = options.valueFormatter || ((cell) => cell.value);
    this.groupedData = new GroupedData(this.rowGroupDefs.concat(this.colGroupDefs));
    this.rowGroups = new GroupedData(this.rowGroupDefs);
    this.colGroups = new GroupedData(this.colGroupDefs);
    if ('source' in options) {
      this.setSource(options.source);
    }
  }

  checkGroupDefs(groupDefs) {
    groupDefs.forEach(groupDef => {
      if (!('accessor' in groupDef)) {
        throw new Error('groupdefs must have an accessor function that returns a group key from a row.');
      }
      if (!('formatter' in groupDef)) {
        // Default formatter
        groupDef.formatter = this.defaultFormatter;
      }
      if (!('sortAccessor' in groupDef)) {
        groupDef.sortAccessor = this.defaultSortAccessor;
      }
      // Create an empty object to map groupKeys back to a sample row.
      groupDef.groupKeyMap = {};
    });
    return groupDefs;
  }

  /**
   * The default formatter for a group def is to present the groupKey.
   * If the th is an autogenerated total, then we prefix with Total.
   *
   * Nb. th.type = total|value
   */
  defaultFormatter(th) {
    return ((th.type === 'total') ? 'Total ' : '') + (th.groupKey || '');
  }
  defaultSortAccessor(group, groupKey) {
    return groupKey;
  }
  statusSortAccessor(group, groupKey) {
    return groupKey;
  }

  setSource(sourceArray) {
    //this.groupedData = new GroupedData(this.rowGroupDefs.concat(this.colGroupDefs));
    //this.rowGroups = new GroupedData(this.rowGroupDefs);
    //this.colGroups = new GroupedData(this.colGroupDefs);

    sourceArray.forEach(row => {

      const rowGroupKeys = [];
      this.rowGroupDefs.forEach((groupDef, groupIndex) => {
        const groupKey = groupDef.accessor(row);
        rowGroupKeys.push(groupKey);

        // Store a map of the generated keys back to a sample row.
        if (!(groupKey in groupDef.groupKeyMap)) { groupDef.groupKeyMap[groupKey] = row; }
      });
      const colGroupKeys = [];
      this.colGroupDefs.forEach((groupDef, groupIndex) => {
        const groupKey = groupDef.accessor(row);
        colGroupKeys.push(groupKey);

        // Store a map of the generated keys back to a sample row.
        if (!(groupKey in groupDef.groupKeyMap)) { groupDef.groupKeyMap[groupKey] = row; }
      });

      // Store row
      this.groupedData.addNestedRow(rowGroupKeys.concat(colGroupKeys), row);
      this.colGroups.addNestedRow(colGroupKeys, row);
      this.rowGroups.addNestedRow(rowGroupKeys, row);
    });
    // Sort everything by the row key.
    this.groupedData.sort();
    this.rowGroups.sort();
    this.colGroups.sort();
  }
  setValueAccessor(f) {
    this.valueAccessor = f;
  }

  /**
   * Return true if all elements of array 'required' is found at the start of array 'candidate'
   */
  sharedAncestry(required, candidate) {
    for (var i=0; i<required.length; i++) {
      if (candidate[i] !== required[i]) {
        return false;
      }
    }
    return true;
  }

  table() {
    var thRows = this.getColHeaders();
    var html = '<table><thead>'
    thRows.forEach(row => {
      html += '<tr>';
      row.forEach(td => { html += `<td colspan="${td.span}">${td.formatted ?? ''}</th>`; });
      html += '</tr>';
    });
    html += '</thead><tbody>';
    this.getRows().forEach(row => {
      html += '<tr>';
      row.forEach(td => { html += `<td>${td.formatted}</td>`; });
      html += '</tr>';
    });
    html += '</tbody><table>';
    return html;
  }


  /**
   * return the row(s) of cells that would go at the top of the output table.
   * Each row will need to be appended to some blanks for the left headers.
   *
   * [
   *  [ {groupKey, colspan}, {groupKey}, ...]
   * ]
   *
   */
  getColHeaders() {
    var colGroups = this.colGroups.getAllGroupKeysFlattened('columns');
    const trs = [];
    for (var i=0; i<this.colGroupDefs.length; i++) {
      // start a row by leaving space for the left header columns.
      trs.push([{span: this.rowGroupDefs.length, formatted:''}]);
      colGroups.forEach(colGroup => {
        if (i in colGroup.cells) {
          colGroup.cells[i].isHeader = true;
          colGroup.cells[i].isColumnHeader = true;
          trs[i].push(colGroup.cells[i]);
        }
      });
    }
    return trs;
  }

  /**
   * Get table cells for all rows.
   */
  getRows() {
    var rowGroups = this.rowGroups.getAllGroupKeysFlattened('rows');
    var colGroups = this.colGroups.getAllGroupKeysFlattened('columns');
    // console.log("colGroups", colGroups); console.log("rowGroups", rowGroups);
    var trs = [];
    var colIdx = 0;
    var rowIdx = 0;
    var currentTotalGroup = '(none)';
    const myData = {};

    rowGroups.forEach((rowGroup, rowGroupIndex) => {
      // copy headers
      const cells = rowGroup.cells.map(cell => { cell.isHeader = true; cell.isRowHeader = true; return cell; });
      var cell;

      if (!rowGroup.isTotal) {
        // Normal row of data
        colGroups.forEach((colGroup, colGroupIndex) => {

          if (!colGroup.isTotal) {
            // Normal data row
            if (!(rowGroupIndex in myData)) {
              myData[rowGroupIndex] = {};
            }
            var rows = this.groupedData.getNested(rowGroup.ancestry.concat(colGroup.ancestry));
            // Sum amounts from data rows
            if (rows) {
              var a = rows.reduce((a, v) => a + this.valueAccessor(v), 0);
              cell = {type: 'value', value: a};
              myData[rowGroupIndex][colGroupIndex] = a;
            }
            else {
              cell = {type: 'value', value: ''};
              myData[rowGroupIndex][colGroupIndex] = 0;
            }
          }
          else {
            // Column is a total in a non-total row.
            var t = 0;
            var sharedAncestry = true;
            for (var ci=colGroupIndex-1;ci>=0 && sharedAncestry;ci--){
              if (!colGroups[ci].isTotal) {
                // Does colGroup[ci] share the same ancestry?
                sharedAncestry = this.sharedAncestry(colGroup.ancestry, colGroups[ci].ancestry);
                if (sharedAncestry) {
                  t += myData[rowGroupIndex][ci];
                }
              }
            }
            // Store the total too.
            myData[rowGroupIndex][colGroupIndex] = t;
            cell = {type: 'total', value: t};
          }
          // Allow reformatting of cell. This function MUST output a string value,
          // and MAY alter or add other properties on the cell.
          cell.colGroup = colGroup;
          cell.rowGroup = rowGroup;
          cell.formatted = this.valueFormatter(cell);
          cells.push(cell);
        });
      }
      else {
        // This row is a total of the rows above it with the sharedAncestry
        colGroups.forEach((colGroup, colGroupIndex) => {
          var t = 0;
          var sharedAncestry = true;
          for (var ri=rowGroupIndex-1;ri>=0 && sharedAncestry;ri--){
            if (!rowGroups[ri].isTotal) {
              sharedAncestry = this.sharedAncestry(rowGroup.ancestry, rowGroups[ri].ancestry);
              if (sharedAncestry) {
                t += myData[ri][colGroupIndex];
              }
            }
          }
          if (!(rowGroupIndex in myData)) { myData[rowGroupIndex] = {}; }
          myData[rowGroupIndex][colGroupIndex] = t;
          cell = {type: 'total', value: t, rowGroup, colGroup};
          // Allow reformatting of cell. This function MUST output a string value,
          // and MAY alter or add other properties on the cell.
          cell.formatted = this.valueFormatter(cell);
          cells.push(cell);
        });
      }
      // Store the row.
      trs.push(cells);
    });

    return trs;
  }

}

  // See https://stackoverflow.com/questions/60230219/how-to-output-an-array-of-different-elements-in-a-sequence-using-angularjs/60231437#60231437
  angular.module('pelf').directive('pelfRepeatSection', function() {
    return {
      restrict: 'A',
      replace: true,
      link(scope, el) { el.remove(); }
    };
  });
  // "pelfPivot" is a directive.
  angular.module('pelf').directive('pelfPivot', function() {
    return {
      restrict: 'E',
      templateUrl: '~/pelf/Pivot.html',
      scope: {
        tableClass: '=',
        sourceRows: '=',
        pivotType: '=',
        projects: '=',
        showAdjusted: '=',
        cases: '=',
        caseStatuses: '=',
      },
      link: function($scope, $el, $attr) {
        var ts = $scope.ts = CRM.ts('pelf');
        // Q. do we want to watch this?
        $scope.$watch('sourceRows', function(newValue) { $scope.sourceRows = newValue; $scope.recalc(); }, true);
        $scope.$watch('showAdjusted', function(newValue) { $scope.recalc(); });
      },
      controller: ['$scope', function pelfPivot($scope) {
        var rowIsValid = row => (row.project && row.fy_start && row.amount);

        const tsTotal = ts('Total') + ' ';

        const projectFormatter = (th) => {
          if (th.groupKey) {
            // Get project value
            th.project = th.groupDef.groupKeyMap[th.groupKey].project;
            return ((th.type === 'total') ? tsTotal : '') + th.groupKey;
          }
          return ((th.type === 'total') ? tsTotal : '');
        };

        const fyFormatter = (th) => {
          const y = parseInt(th.groupKey.substr(0, 4));
          if (th.groupKey.substr(-5) === '01-01') {
            return y;
          }
          else {
            return `${y}-${y+1}`;
          }
        };

        $scope.recalc = function recalc() {

          const pivotConfig = {};
          if (this.showAdjusted) {
            pivotConfig.valueAccessor = row => Math.round(parseFloat(row.amount) * $scope.cases[row.case_id].worth_percent / 100);
          }
          else {
            pivotConfig.valueAccessor = row => parseFloat(row.amount);
          }
          // We need the max value in our data.
          // var maxValue = this.sourceRows.reduce((a, v) => {console.log("Considering", pivotConfig.valueAccessor(v)); return Math.max(a, pivotConfig.valueAccessor(v));}, 0);
          var maxValue = 0;

          pivotConfig.valueFormatter = cell => {
            // Use this loop to determine the max value
            maxValue = Math.max(maxValue, cell.value);
            return Math.round(cell.value).toLocaleString();
          };

          if ($scope.pivotType === 'full') {

            pivotConfig.rowGroupDefs = [
              { name: 'Project',
                accessor: row => $scope.projects[row.project].label.replace(/\s*:.*$/, ''),
                total: true,
                formatter: projectFormatter,
              },
              { name: 'Project detail', accessor: row => $scope.projects[row.project].label.replace(/^.*\s*:/, ''), total: true },
            ];
            pivotConfig.colGroupDefs = [
              { name: 'Year', accessor: row => row.fy_start, total: true, formatter: fyFormatter }
            ];

          }
          if ($scope.pivotType === 'by_status') {

            pivotConfig.rowGroupDefs = [
              { name: 'Status',
                accessor: row => $scope.cases[row.case_id].status_id,
                sortAccessor: (group, groupKey) =>  parseInt($scope.caseStatuses[groupKey].weight),
                formatter: th => {
                  th.status = $scope.caseStatuses[th.groupKey];
                  th.cellClasses = 'pelf-financial__no-padding';
                  return ((th.type === 'total') ? tsTotal : '') + th.status.label;
                },
                total: true,
              }
            ];
            pivotConfig.colGroupDefs = [
              { name: 'Year', accessor: row => row.fy_start, total: true, formatter: fyFormatter }
            ];

            // Special
            pivotConfig.valueFormatter = cell => {
              // Use this loop to determine the max value
              maxValue = Math.max(maxValue, cell.value);
              if (cell.rowGroup.cells[0] && cell.rowGroup.cells[0].status) {
                cell.barColour = cell.rowGroup.cells[0].status.color;
              }
              return Math.round(cell.value).toLocaleString();
            };

          }


          console.log("recalc", pivotConfig);
          var p = new Pivot(pivotConfig);
          p.setSource(this.sourceRows);
          $scope.thRows = p.getColHeaders();
          $scope.tdRows = p.getRows();

          // Calc maxValue in the table.
          //$scope.maxValue = $scope.tdRows.reduce((a, row) => row.reduce((a, cell) => cell.isHeader ? a : Math.max(a, cell.value),0), 0);
          console.warn("maxValue", maxValue);
          $scope.maxValue = maxValue;
        }
        $scope.recalc();

      }]
    };
  });
})(angular, CRM.$, CRM._);
