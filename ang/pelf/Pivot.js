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

        groups.push({ accessor: row => row.project, type: 'row', formatter: row => $scope.projects[row.project].label });
        groups.push({ accessor: row => row.fy_start, type: 'column', formatter: row => row.fy_start });
        // groups.push({ accessor: row => row.amount, type: 'value', formatter: row => row.amount });

        $scope.recalc = function recalc() {

          const index = {groups: {}, total: 0};
          const headers = {row: {}, column: {}};
          _.forEach($scope.sourceRows, (row, rowIdx) => {

            var t = index;
            const rowValue = valueAccessor(row);
            t.total += rowValue;

            _.forEach(groups, g => {
              const groupKey = g.accessor(row);

              if (!(groupKey in t.groups)) {
                t.groups[groupKey] = {groups:{}, total: 0};
              }
              t = t.groups[groupKey];

              // Accumulate the value
              t.total += rowValue;

              // Collect unique headers
              if (!(groupKey in headers[g.type])) {
                headers[g.type][groupKey] = {label: g.formatter(row), groupKey, total: rowValue};
              }
              else {
                headers[g.type][groupKey].total += rowValue;
              }
            });
          });
          // Now we have structured data with totals.

          // Sort headers
          $scope.rowHeads =_.sortBy(_.values(headers.row), 'label');
          $scope.colHeads =_.sortBy(_.values(headers.column), 'label');

          $scope.totals = index; // @todo rename index?

        }

        $scope.recalc();

      }]
    };
  });
})(angular, CRM.$, CRM._);
