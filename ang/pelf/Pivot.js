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

        var rowGroups = [
          { accessor: row => $scope.projects[row.project].label.replace(/\s*:.*$/, '') },
          { accessor: row => $scope.projects[row.project].label.replace(/^.*\s*:/, '') }
        ];
        var colGroups = [
          { accessor: row => row.fy_start }
        ];
        $scope.recalc = function recalc() {

          var t, ths;
          const topThs = {subgroups: {}, total: 0};
          const leftThs = {subgroups: {}, total: 0};

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

          const index = {groups: {}, total: 0};

          _.forEach($scope.sourceRows, (row, rowIdx) => {
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

          // Sort the headers.
          // Generate rows
          $scope.htmlRows = [];

          var leftTh = leftThs;
          var topTh = topThs;
          var maxDepth = 0;
          t = index;

          function processRowGroups(cells, depth) {

            if (depth == 0) {
              console.log(`${depth}: new row`);
              cells = {th: [], td: []}
            }
            maxDepth = Math.max(maxDepth, depth);
            // Loop all the groups
            _.forEach(_.sortBy(_.keys(leftTh.subgroups)), groupKey => {
              // 'climate'
              // 'training'
              cells.th.push({type:'th', text: groupKey});
              console.log(`${depth}: added leftTh`,groupKey, JSON.stringify(cells.th));
              // Descend into the data by this group
              const lastDataPosition = t;
              t = t.groups[groupKey];

              // Does this group have any sub groups?
              if (_.keys(leftTh.subgroups[groupKey].subgroups).length > 0) {
                console.log(`${depth}: there are more ths to do:`, {leftTh});
                const lastLeftTh = leftTh;
                leftTh = leftTh.subgroups[groupKey];
                processRowGroups(cells, depth + 1);
                leftTh = lastLeftTh;
              }
              else {
                console.log(`${depth}: there are no more leftThs to do, doing topThs`);
                // Now add in data from columns
                topTh = topThs;
                processColumnGroups(cells);
                // Completed a row
                $scope.htmlRows.push(_.cloneDeep(cells));
                console.log(`${depth}: completed a row ${JSON.stringify(cells)}`);
                // Reset cells
                cells.td = [];
              }
              t = lastDataPosition;
              cells.th.pop();
            });
          }
          function processColumnGroups(cells) {
            _.forEach(_.sortBy(_.keys(topTh.subgroups)), groupKey => {
              // Data cells
              console.log("cat", {t,groupKey});

              if (groupKey in t.groups) {
                // We have a value
                cells.td.push({type:'td', text: t.total});
              }
              else {
                cells.td.push({type:'td', text: ''});
              }
             //// Repeat for sub keys
             //if (_.keys(leftTh.subgroups).length > 0) {
             //  processColumnGroups();
             //}
              // Now add in data from columns
              //processColumnGroups();
            });
          }

          processRowGroups([], 0);
          // Create top header row.
          const cells = {th: [], td: []};
          while (maxDepth >= 0) {
            cells.th.push({text: ''});
            maxDepth--;
          }
          _.forEach(_.sortBy(_.keys(topThs.subgroups)), groupKey => { cells.th.push({text: groupKey}); });
          $scope.htmlRows.unshift(cells);


          // Now we have structured data with totals.

          // Sort headers
          //$scope.rowHeads =_.sortBy(_.values(headers.row), 'label');
          ////$scope.colHeads =_.sortBy(_.values(headers.column), 'label');

          $scope.totals = index; // @todo rename index?

        }
        $scope.recalc();

      }]
    };
  });
})(angular, CRM.$, CRM._);
