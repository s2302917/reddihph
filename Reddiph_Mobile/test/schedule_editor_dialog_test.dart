import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:reddiph/views/widgets/schedule_editor_dialog.dart';

void main() {
  testWidgets('previews and returns every date in a date range',
      (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final result = <ScheduleDraft?>[];
    await tester.pumpWidget(MaterialApp(
      home: Builder(
        builder: (context) => Scaffold(
          body: TextButton(
            onPressed: () async {
              result.add(await showScheduleEditorDialog(
                context: context,
                initialDate: DateTime(2026, 1, 5),
                typeOptions: const {'morning': 'Morning'},
                initialType: 'morning',
                initialStart: '07:00',
                initialEnd: '15:00',
              ));
            },
            child: const Text('Open'),
          ),
        ),
      ),
    ));
    await tester.tap(find.text('Open'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    await tester.tap(find.text('Date range'));
    await tester.pumpAndSettle();
    await tester.enterText(
        find.byKey(const ValueKey('Start date')), '2026-03-01');
    await tester.enterText(
        find.byKey(const ValueKey('End date')), '2026-03-03');
    await tester.pump();

    expect(find.textContaining('3 dates'), findsOneWidget);
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(result.single!.dates, [
      DateTime(2026, 3, 1),
      DateTime(2026, 3, 2),
      DateTime(2026, 3, 3),
    ]);
  });

  testWidgets('repeats the same schedule weekly until the end date',
      (tester) async {
    final results = <ScheduleDraft?>[];
    await tester.pumpWidget(MaterialApp(
      home: Builder(
        builder: (context) => Scaffold(
          body: TextButton(
            onPressed: () async {
              results.add(await showScheduleEditorDialog(
                context: context,
                initialDate: DateTime(2026, 1, 5),
                typeOptions: const {'morning': 'Morning'},
                initialType: 'morning',
                initialStart: '07:00',
                initialEnd: '15:00',
              ));
            },
            child: const Text('Open'),
          ),
        ),
      ),
    ));
    await tester.tap(find.text('Open'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Repeat'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const ValueKey('Repeat until')),
      '2026-01-19',
    );
    await tester.tap(find.byType(DropdownButtonFormField<String>).first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Every week').last);
    await tester.pumpAndSettle();

    expect(find.textContaining('3 dates'), findsOneWidget);
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(results.single!.dates, [
      DateTime(2026, 1, 5),
      DateTime(2026, 1, 12),
      DateTime(2026, 1, 19),
    ]);
    expect(results.single!.start, '07:00');
    expect(results.single!.end, '15:00');
  });

  testWidgets('repeats only on selected weekdays', (tester) async {
    final results = <ScheduleDraft?>[];
    await tester.pumpWidget(MaterialApp(
      home: Builder(
        builder: (context) => Scaffold(
          body: TextButton(
            onPressed: () async {
              results.add(await showScheduleEditorDialog(
                context: context,
                initialDate: DateTime(2026, 3, 2),
                typeOptions: const {'morning': 'Morning'},
                initialType: 'morning',
                initialStart: '07:00',
                initialEnd: '15:00',
              ));
            },
            child: const Text('Open'),
          ),
        ),
      ),
    ));
    await tester.tap(find.text('Open'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Repeat'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const ValueKey('Repeat until')),
      '2026-03-08',
    );
    await tester.tap(find.byType(DropdownButtonFormField<String>).first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Selected days of the week').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Fri'));
    await tester.pumpAndSettle();

    expect(find.textContaining('2 dates'), findsOneWidget);
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(results.single!.dates, [
      DateTime(2026, 3, 2),
      DateTime(2026, 3, 6),
    ]);
  });
}
