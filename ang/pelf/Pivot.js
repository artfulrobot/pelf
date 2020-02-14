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
    if (this.childGroups.count()) {
      this.childGroups.sortBy((v, k) => k);
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
      // Create an empty object to map groupKeys back to a sample row.
      groupDef.groupKeyMap = {};
    });
    return groupDefs;
  }

  defaultFormatter(th) {
    return ((th.type === 'total') ? 'Total ' : '') + (th.groupKey || '');
  }

  setSource(sourceArray) {
    this.groupedData = new GroupedData(this.rowGroupDefs.concat(this.colGroupDefs));
    this.rowGroups = new GroupedData(this.rowGroupDefs);
    this.colGroups = new GroupedData(this.colGroupDefs);

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
      const cells = rowGroup.cells;

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
              cells.push({formatted: a, type: 'value'});
              myData[rowGroupIndex][colGroupIndex] = a;
            }
            else {
              cells.push({formatted: ''});
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
            cells.push({formatted: t, type: 'total'});
          }
          // End of a cell.
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
          cells.push({formatted: t, type: 'total'});
        });
      }
      // Store the row.
      trs.push(cells);
    });

    return trs;
  }

}
  // "pelfPivot" is a directive.
  angular.module('pelf').directive('pelfPivot', function() {
    return {
      restrict: 'E',
      templateUrl: '~/pelf/Pivot.html',
      scope: {
        sourceRows: '=',
        pivotType: '=',
        projects: '=',
      },
      link: function($scope, $el, $attr) {
        var ts = $scope.ts = CRM.ts('pelf');
        $scope.$watch('sourceRows', function(newValue) { $scope.sourceRows = newValue; $scope.recalc(); }, true);
      },
      controller: ['$scope', function pelfPivot($scope) {

        var rowIsValid = row => (row.project && row.fy_start && row.amount);

        const tsTotal = ts('Total');
        const projectFormatter = (th) => {
          if (th.groupKey) {
            // Get project value
            th.project = th.groupDef.groupKeyMap[th.groupKey].project;
            return ((th.type === 'total') ? tsTotal + ' ' : '') + th.groupKey;
          }
          return ((th.type === 'total') ? tsTotal : '');
        };

        $scope.recalc = function recalc() {

          const pivotConfig = {
            valueAccessor: row => parseFloat(row.amount)
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
              { name: 'Year', accessor: row => row.fy_start, total: true }
            ];

          }


          var p = new Pivot(pivotConfig);
          console.log("setting source to ", this.sourceRows);
          $scope.thRows = p.getColHeaders();
          $scope.tdRows = p.getRows();
        }
        $scope.recalc();

      }]
    };
  });
})(angular, CRM.$, CRM._);
