(function(angular, $, _) {
  // "pelfPivot" is a basic skeletal directive.
  // Example usage: <pelf-pivot ="{foo: 1, bar: 2}" />
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

        const totals = {};
        var valueAccessor = row => parseFloat(row.amount), groups = [];
        var rowIsValid = row => (row.project && row.fy_start && row.amount);

        var rowGroups = [
          { accessor: row => $scope.projects[row.project].label.replace(/\s*:.*$/, '') },
          { accessor: row => $scope.projects[row.project].label.replace(/^.*\s*:/, '') }
        ];
        var colGroups = [
          { accessor: row => row.fy_start }
        ];


        $scope.recalc = function recalc() {

          //
          // First calculate totals for the data per groupings.
          //
          const rowGroupsStack = [];
          var t, ths;
          const topThs = {subgroups: {}, total: 0};
          const leftThs = {subgroups: {}, total: 0};
          const index = {groups: {}, total: 0};
          function addTotals(row, rowValue, g) {
            // Add value to totals as we go down the groupings.
            // This gives us a total per bigger group as well as the final total.
            const groupKey = g.accessor(row);

            if (!(groupKey in t.groups)) {
              t.groups[groupKey] = {groups:{}, total: 0};
            }
            t = t.groups[groupKey];
            t.total += rowValue;

            if (!(groupKey in ths.subgroups)) {
              ths.subgroups[groupKey] = {subgroups: {}, total: 0};
            }
            ths = ths.subgroups[groupKey];
            ths.total += rowValue;
          }
          _.forEach($scope.sourceRows, (row, rowIdx) => {
            // Skip invalid rows
            if (!rowIsValid(row)) return;
            t = index;
            const rowValue = valueAccessor(row);
            t.total += rowValue;
            ths = leftThs;
            _.forEach(rowGroups, g => {
              // Add value to totals as we go down the groupings.
              // This gives us a total per bigger group as well as the final total.
              addTotals(row, rowValue, g);
            });
            ths = topThs;
            _.forEach(colGroups, g => {
              addTotals(row, rowValue, g);
            });

          });

          console.log({index, topThs, leftThs});

          //
          // Generate rows for HTML
          //
          $scope.htmlRows = [];

          var leftTh = leftThs;
          var topTh = topThs;
          var maxDepth = 0;
          t = index; // At any point, 't' points to an object nested in the index. This will have a total and a subgroups key.
          // We need totals for: index2.row1.(col1 - n)

          function processRowGroups(cells, depth, parentGroupSubtotals) {


            if (depth == 0) {
              // Initial call.
              cells = {th: [], td: []}
            }

            maxDepth = Math.max(maxDepth, depth);

            // Loop all the groups
            _.forEach(_.sortBy(_.keys(leftTh.subgroups)), groupKey => {
              // 'climate'
              // 'training'

              cells.th.push({type:'th', text: groupKey});
              // console.log(`${depth}: added leftTh`,groupKey, JSON.stringify(cells.th));
              // Descend into the data by this group
              const lastDataPosition = t;
              t = t.groups[groupKey];

              // Does this group have any sub groups?
              var groupHasSubgroups = _.keys(leftTh.subgroups[groupKey].subgroups).length  > 0;
              if (groupHasSubgroups) {
                // There are subgroups within this group.

                // console.log(`${depth}: there are more ths to do:`, {leftTh});
                const lastLeftTh = leftTh;
                leftTh = leftTh.subgroups[groupKey];
                // Recurse into these groups.
                var myGroupSubtotals = {total: 0};
                _.each(_.keys(topTh.subgroups), groupKey => { myGroupSubtotals[groupKey] = 0; });
                processRowGroups(cells, depth + 1, myGroupSubtotals);
                leftTh = lastLeftTh;
                // Add subgroups' subtotals to our subtotals.
                _.each(_.keys(topTh.subgroups), groupKey => { parentGroupSubtotals[groupKey] += myGroupSubtotals[groupKey]; });
                parentGroupSubtotals.total += myGroupSubtotals.total;

                // Add a total row for this subgroup.
                console.log(`Need to add subtotal row for ${groupKey} in`, _.cloneDeep(myGroupSubtotals));

                cells.th.push({text: 'Subtotal'});
                _.forEach(_.sortBy(_.keys(topTh.subgroups)), colKey => {
                  cells.td.push({text: myGroupSubtotals[colKey] });
                });
                cells.td.push({text: myGroupSubtotals.total });
                $scope.htmlRows.push(_.cloneDeep(cells));
                cells.th.pop();
                cells.td = [];
              }
              else {
                // console.log(`${depth}: there are no more leftThs to do, doing topThs`);
                // Now add in data from columns
                topTh = topThs;
                processColumnGroups(cells, parentGroupSubtotals);
                console.log(`After adding rowtotals subtotal is now `, _.cloneDeep(parentGroupSubtotals));

                // Add Total column
                cells.td.push({text: leftTh.subgroups[groupKey].total });
                parentGroupSubtotals.total += leftTh.subgroups[groupKey].total;
                // Completed a row
                $scope.htmlRows.push(_.cloneDeep(cells));
                // console.log(`${depth}: completed a row ${JSON.stringify(cells)}`);
                // Reset cells
                cells.td = [];
              }
              t = lastDataPosition;
              cells.th.pop();

            });
          }
          function processColumnGroups(cells, subtotals) {
            _.forEach(_.sortBy(_.keys(topTh.subgroups)), groupKey => {
              // Data cells
              //console.log("cat", {t,groupKey});

              if (groupKey in t.groups) {
                // We have a value
                cells.td.push({type:'td', text: t.groups[groupKey].total});
                subtotals[groupKey] = (subtotals[groupKey] ?? 0) + t.groups[groupKey].total;
              }
              else {
                cells.td.push({type:'td', text: ''});
              }
            });
          }

          var columnTotals = {total:0};
          _.each(_.keys(topTh.subgroups), groupKey => { columnTotals[groupKey] = 0; });
          processRowGroups([], 0, columnTotals);
          //
          // Create top header row.
          //
          const cells = {th: [], td: []};
          for (i=maxDepth;i>=0;i--) {
            cells.th.push({text: ''});
          }
          _.forEach(_.sortBy(_.keys(topThs.subgroups)), groupKey => { cells.th.push({text: groupKey}); });
          cells.th.push({text: 'Total'});
          $scope.htmlRows.unshift(_.cloneDeep(cells));
          //
          // Create totals row at bottom
          //
          cells.th = [{text: 'Total'}];
          cells.td = [];
          for (i=maxDepth;i>0;i--) {
            cells.th.push({text: ''});
          }
          _.forEach(_.sortBy(_.keys(topThs.subgroups)), groupKey => { cells.td.push({text: columnTotals[groupKey]}); });
          cells.td.push({text: columnTotals.total});
          $scope.htmlRows.push(_.cloneDeep(cells));


          // Now we have structured data with totals.

          // Sort headers
          //$scope.rowHeads =_.sortBy(_.values(headers.row), 'label');
          ////$scope.colHeads =_.sortBy(_.values(headers.column), 'label');

          $scope.totals = index; // @todo rename index?

          console.log({index, topThs, leftThs});

        }
        $scope.recalc();

      }]
    };
  });
})(angular, CRM.$, CRM._);
